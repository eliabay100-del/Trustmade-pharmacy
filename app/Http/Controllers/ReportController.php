<?php

namespace App\Http\Controllers;

use App\Models\IssuedItem;
use App\Models\PurchaseBatch;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\Account;
use App\Exports\MonthlyAccountingExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function inventory()
    {
        $user = auth()->user();
        $query = PurchaseBatch::with(['drug', 'branch'])->where('quantity_remaining', '>', 0);
        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }
        $stock = $query->orderBy('expiry_date')->get();

        return view('reports.inventory', compact('stock'));
    }

    public function financial()
    {
        $user = auth()->user();
        $query = JournalEntry::query();
        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }
        $entries = $query->latest()->paginate(20);

        return view('reports.financial', compact('entries'));
    }

    public function sales()
    {
        $user = auth()->user();

        $query = IssuedItem::with(['purchaseBatch.drug', 'customer']);
        $totalQuery = IssuedItem::query();
        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id);
            $totalQuery->where('branch_id', $user->branch_id);
        }
        $sales = $query->latest()->paginate(20);
        $total = $totalQuery->sum('total_amount');

        return view('reports.sales', compact('sales', 'total'));
    }

    public function exportForm()
    {
        return view('reports.export');
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        // NOTE: the export currently scopes to one branch_id (nullable
        // for Owner/Admin). MonthlyAccountingExport's underlying sheet
        // queries use where('branch_id', $branchId) directly — for an
        // Owner/Admin exporting across all branches, this needs the same
        // null-safe treatment as above. Flagging rather than silently
        // changing the Export classes' query shape in this pass — worth
        // a dedicated look if Owner/Admin needs a consolidated export.
        $branchId = auth()->user()->branch_id;
        $filename = 'pmas-accounting-' . $validated['year'] . '-' . str_pad($validated['month'], 2, '0', STR_PAD_LEFT) . '.xlsx';

        return Excel::download(
            new MonthlyAccountingExport($branchId, $validated['month'], $validated['year']),
            $filename
        );
    }

    /**
     * Trial Balance Report
     * Shows sum of debits and credits per account, with net balance.
     */
    public function trialBalance(Request $request)
    {
        $user = auth()->user();
        
        $query = Ledger::query()->selectRaw('account_id, SUM(debit) as total_debit, SUM(credit) as total_credit');
        
        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }
        
        $query->groupBy('account_id');
        
        $balances = $query->get()->map(function ($item) {
            return [
                'account_id' => $item->account_id,
                'total_debit' => $item->total_debit ?? 0,
                'total_credit' => $item->total_credit ?? 0,
                'balance' => ($item->total_debit ?? 0) - ($item->total_credit ?? 0),
            ];
        });
        
        // Load account details
        $accounts = Account::whereIn('id', $balances->pluck('account_id'))->get()->keyBy('id');
        
        $trialBalance = $balances->map(function ($item) use ($accounts) {
            $account = $accounts[$item['account_id']] ?? null;
            return [
                'code' => $account?->code ?? 'N/A',
                'name' => $account?->name ?? 'Unknown Account',
                'type' => $account?->type ?? 'unknown',
                'debit' => $item['total_debit'],
                'credit' => $item['total_credit'],
                'balance' => $item['balance'],
            ];
        });
        
        $totalDebit = $trialBalance->sum('debit');
        $totalCredit = $trialBalance->sum('credit');
        
        return view('reports.trial-balance', compact('trialBalance', 'totalDebit', 'totalCredit'));
    }

    /**
     * Income Statement Report
     * Revenue accounts minus COGS and expense accounts, grouped by Account::type
     */
    public function incomeStatement(Request $request)
    {
        $user = auth()->user();
        
        $query = Ledger::query()
            ->join('accounts', 'ledgers.account_id', '=', 'accounts.id')
            ->selectRaw('accounts.type, accounts.code, accounts.name, SUM(ledgers.debit) as total_debit, SUM(ledgers.credit) as total_credit');
        
        if ($user->branch_id) {
            $query->where('ledgers.branch_id', $user->branch_id);
        }
        
        $query->groupBy('accounts.type', 'accounts.code', 'accounts.name');
        $query->orderBy('accounts.code');
        
        $entries = $query->get();
        
        // Group by type and calculate net amounts
        $grouped = $entries->groupBy('type');
        
        $revenue = $grouped->get('revenue', collect())->map(function ($item) {
            return [
                'code' => $item->code,
                'name' => $item->name,
                'amount' => $item->total_credit - $item->total_debit, // Revenue has credit balance
            ];
        });
        
        $cogs = $grouped->get('expense', collect())->filter(function ($item) {
            return strpos($item->code, '5000') === 0 || $item->name === 'Cost of Goods Sold';
        })->map(function ($item) {
            return [
                'code' => $item->code,
                'name' => $item->name,
                'amount' => $item->total_debit - $item->total_credit, // Expenses have debit balance
            ];
        });
        
        $expenses = $grouped->get('expense', collect())->filter(function ($item) {
            return strpos($item->code, '5000') !== 0 && $item->name !== 'Cost of Goods Sold';
        })->map(function ($item) {
            return [
                'code' => $item->code,
                'name' => $item->name,
                'amount' => $item->total_debit - $item->total_credit,
            ];
        });
        
        $totalRevenue = $revenue->sum('amount');
        $totalCogs = $cogs->sum('amount');
        $totalExpenses = $expenses->sum('amount');
        $grossProfit = $totalRevenue - $totalCogs;
        $netIncome = $grossProfit - $totalExpenses;
        
        return view('reports.income-statement', compact(
            'revenue', 'cogs', 'expenses',
            'totalRevenue', 'totalCogs', 'totalExpenses',
            'grossProfit', 'netIncome'
        ));
    }
}
