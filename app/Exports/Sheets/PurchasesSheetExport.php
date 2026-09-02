<?php

namespace App\Exports\Sheets;

use App\Models\PurchaseBatch;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PurchasesSheetExport implements FromCollection, WithTitle, WithHeadings, WithMapping
{
    protected $branchId;
    protected $month;
    protected $year;

    public function __construct($branchId, $month, $year)
    {
        $this->branchId = $branchId;
        $this->month = $month;
        $this->year = $year;
    }

    // Same v4 fix as SalesSheetExport — explicit Enumerable return type.
    public function collection(): Enumerable
    {
        return PurchaseBatch::with(['drug', 'supplier'])
            ->where('branch_id', $this->branchId)
            ->whereMonth('purchase_date', $this->month)
            ->whereYear('purchase_date', $this->year)
            ->orderBy('purchase_date')
            ->get();
    }

    public function map($batch): array
    {
        return [
            \Carbon\Carbon::parse($batch->purchase_date)->format('Y-m-d'),
            $batch->drug->name ?? '—',
            $batch->supplier->name ?? '—',
            $batch->batch_number,
            $batch->quantity_received,
            round($batch->cost_per_unit, 2),
            round($batch->quantity_received * $batch->cost_per_unit, 2),
        ];
    }

    public function headings(): array
    {
        return ['Date', 'Drug', 'Supplier', 'Batch #', 'Quantity Received', 'Cost/Unit', 'Total Cost'];
    }

    public function title(): string
    {
        return 'Purchases';
    }
}
