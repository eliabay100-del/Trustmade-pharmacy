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

    public function sales(Request $request)
    {
        $user = auth()->user();

        // Optional month / year narrowing. Values are sanitised rather than
        // validated: a hand-edited URL that says month=13 should quietly mean
        // "all months", not bounce the user off the report with an error.
        $month = $request->integer('month');
        $month = $month >= 1 && $month <= 12 ? $month : null;

        $year = $request->integer('year');
        $year = $year >= 2000 && $year <= 2100 ? $year : null;

        // A month on its own means the current year - nobody wants "every
        // March since we opened". A year on its own covers all twelve months.
        // Leaving both blank keeps the all-time view this screen always showed.
        if ($month && ! $year) {
            $year = (int) now()->year;
        }

        $range = $this->monthRange($month, $year);

        $query = IssuedItem::with(['purchaseBatch.drug', 'purchaseBatch.product', 'customer']);
        $totalQuery = IssuedItem::query();
        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id);
            $totalQuery->where('branch_id', $user->branch_id);
        }
        if ($range) {
            $query->whereBetween('issue_date', $range);
            $totalQuery->whereBetween('issue_date', $range);
        }

        // The grand total has to cover every matching row, not just the page.
        $total = (float) $totalQuery->sum('total_amount');
        $sales = $query->latest()->paginate(20)->withQueryString();

        // Every year from the first sale up to this one, so a figure typed into
        // the URL by hand still selects something sensible.
        $firstSale = $totalQuery->min('issue_date');
        $firstYear = $firstSale ? (int) \Carbon\Carbon::parse($firstSale)->year : (int) now()->year;
        $currentYear = (int) now()->year;
        $years = range($currentYear, max(2000, min($firstYear, $currentYear)));

        return view('reports.sales', compact('sales', 'total', 'month', 'year', 'years'));
    }

    /**
     * Half-open-ish date range covering a whole month or a whole year, or null
     * when nothing was picked.
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}|null
     */
    protected function monthRange(?int $month, ?int $year): ?array
    {
        if ($month && $year) {
            $start = \Carbon\Carbon::create($year, $month, 1)->startOfDay();

            return [$start, $start->copy()->endOfMonth()->endOfDay()];
        }

        if ($year) {
            return [
                \Carbon\Carbon::create($year, 1, 1)->startOfDay(),
                \Carbon\Carbon::create($year, 12, 31)->endOfDay(),
            ];
        }

        return null;
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

        // Null for Owner/Admin, meaning "all branches". Every sheet in
        // MonthlyAccountingExport treats a null branch as a consolidated
        // view rather than a literal "branch_id IS NULL" match.
        $branchId = auth()->user()->branch_id;
        $filename = 'pmas-accounting-' . $validated['year'] . '-' . str_pad($validated['month'], 2, '0', STR_PAD_LEFT) . '.xlsx';

        return Excel::download(
            new MonthlyAccountingExport($branchId, $validated['month'], $validated['year']),
            $filename
        );
    }
}
