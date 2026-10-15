<?php

namespace Tests\Feature;

use App\Exports\Sheets\PurchasesSheetExport;
use App\Exports\Sheets\SummarySheetExport;
use App\Models\Branch;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\PurchaseBatch;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function batch(int $branchId, float $totalCost = 100.0): PurchaseBatch
    {
        $category = DrugCategory::firstOrCreate(['name' => 'Analgesics']);
        $drug = Drug::firstOrCreate(
            ['name' => 'Paracetamol'],
            ['category_id' => $category->id, 'unit_of_measure' => 'tablet', 'units_per_strip' => 10, 'strips_per_pack' => 10]
        );

        return PurchaseBatch::create([
            'drug_id' => $drug->id,
            'supplier_id' => Supplier::firstOrCreate(['name' => 'Test Supplier'])->id,
            'branch_id' => $branchId,
            'batch_number' => 'B-' . uniqid(),
            'quantity_received' => 10,
            'quantity_remaining' => 10,
            'cost_per_unit' => $totalCost / 10,
            'selling_price_per_unit' => ($totalCost / 10) * 2,
            'expiry_date' => now()->addYear(),
            'purchase_date' => now(),
        ]);
    }

    /**
     * Regression: a null branch used to compile every sheet query to
     * "branch_id IS NULL", so an all-branch Owner/Admin export came out
     * empty instead of consolidating the branches.
     */
    public function test_consolidated_export_includes_purchases_from_every_branch(): void
    {
        $branchA = Branch::create(['name' => 'Branch A']);
        $branchB = Branch::create(['name' => 'Branch B']);
        $this->batch($branchA->id, 100.0);
        $this->batch($branchB->id, 50.0);

        $month = (int) now()->month;
        $year = (int) now()->year;

        $this->assertEquals(2, (new PurchasesSheetExport(null, $month, $year))->collection()->count());

        $summary = collect((new SummarySheetExport(null, $month, $year))->array());
        $this->assertEquals(2, $summary->first(fn ($row) => ($row[0] ?? null) === 'Number of Purchase Batches Received')[1]);
        $this->assertEqualsWithDelta(150.0, (float) $summary->first(fn ($row) => ($row[0] ?? null) === 'Total Purchases (New Stock Cost)')[1], 0.001);
    }

    public function test_branch_scoped_export_only_includes_that_branch(): void
    {
        $branchA = Branch::create(['name' => 'Branch A']);
        $branchB = Branch::create(['name' => 'Branch B']);
        $this->batch($branchA->id, 100.0);
        $this->batch($branchB->id, 50.0);

        $month = (int) now()->month;
        $year = (int) now()->year;

        $this->assertEquals(1, (new PurchasesSheetExport($branchA->id, $month, $year))->collection()->count());
    }
}
