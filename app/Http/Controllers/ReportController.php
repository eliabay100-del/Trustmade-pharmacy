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
        $query->with('product');
        if ($request = request()) {
            if ($request->input('type') === 'drug') $query->whereNotNull('drug_id');
            if ($request->input('type') === 'product') $query->whereNotNull('product_id');
        }
        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }
        $stock = $query->with('product')->orderBy('expiry_date')->get();

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

        $query = IssuedItem::with(['purchaseBatch.drug', 'purchaseBatch.product', 'customer']);
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
}
