<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\PurchaseBatch;
use App\Models\Supplier;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    protected TransferService $transfer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transfer = new TransferService();
    }

    protected function drug(): Drug
    {
        return Drug::create([
            'name' => 'Paracetamol',
            'category_id' => DrugCategory::create(['name' => 'Analgesics'])->id,
            'unit_of_measure' => 'tablet',
            'units_per_strip' => 10,
            'strips_per_pack' => 10,
        ]);
    }

    protected function batch(int $drugId, int $branchId, array $overrides = []): PurchaseBatch
    {
        return PurchaseBatch::create(array_merge([
            'drug_id' => $drugId,
            'supplier_id' => Supplier::firstOrCreate(['name' => 'Test Supplier'])->id,
            'branch_id' => $branchId,
            'batch_number' => 'B-' . uniqid(),
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'cost_per_unit' => 1.00,
            'selling_price_per_unit' => 2.00,
            'expiry_date' => now()->addYear(),
            'purchase_date' => now(),
        ], $overrides));
    }

    /**
     * Regression: the transactional closure used to capture a non-existent
     * $drugId and never captured the $itemType/$itemId parameters, so every
     * transfer queried drug_id = null and failed with "Insufficient stock".
     */
    public function test_transfer_moves_stock_from_source_to_destination_branch(): void
    {
        $from = Branch::create(['name' => 'Main Branch']);
        $to = Branch::create(['name' => 'Second Branch']);
        $drug = $this->drug();
        $source = $this->batch($drug->id, $from->id, ['quantity_remaining' => 40]);

        $transfer = $this->transfer->transfer('drug', $drug->id, $from->id, $to->id, 15, 1);

        $this->assertNotNull($transfer);
        $this->assertEquals(15, $transfer->quantity);
        $this->assertEquals($from->id, $transfer->from_branch_id);
        $this->assertEquals($to->id, $transfer->to_branch_id);

        // Source batch reduced, destination batch created with the moved units.
        $this->assertEquals(25, $source->fresh()->quantity_remaining);
        $dest = PurchaseBatch::where('branch_id', $to->id)->firstOrFail();
        $this->assertEquals(15, $dest->quantity_remaining);
        $this->assertEquals($drug->id, $dest->drug_id);

        $this->assertDatabaseHas('stock_cards', [
            'branch_id' => $from->id,
            'transaction_type' => 'transfer_out',
            'quantity' => 15,
        ]);
        $this->assertDatabaseHas('stock_cards', [
            'branch_id' => $to->id,
            'transaction_type' => 'transfer_in',
            'quantity' => 15,
        ]);
    }

    public function test_transfer_spanning_multiple_batches_is_allowed(): void
    {
        $from = Branch::create(['name' => 'Main Branch']);
        $to = Branch::create(['name' => 'Second Branch']);
        $drug = $this->drug();
        $this->batch($drug->id, $from->id, ['quantity_remaining' => 10, 'expiry_date' => now()->addMonth()]);
        $this->batch($drug->id, $from->id, ['quantity_remaining' => 10, 'expiry_date' => now()->addMonths(6)]);

        $this->transfer->transfer('drug', $drug->id, $from->id, $to->id, 15, 1);

        $this->assertEquals(15, PurchaseBatch::where('branch_id', $to->id)->sum('quantity_remaining'));
        $this->assertEquals(5, PurchaseBatch::where('branch_id', $from->id)->sum('quantity_remaining'));
    }

    public function test_transfer_rejects_the_same_source_and_destination(): void
    {
        $branch = Branch::create(['name' => 'Main Branch']);
        $drug = $this->drug();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Source and destination must differ.');

        $this->transfer->transfer('drug', $drug->id, $branch->id, $branch->id, 1, 1);
    }

    public function test_transfer_rejects_more_than_is_available(): void
    {
        $from = Branch::create(['name' => 'Main Branch']);
        $to = Branch::create(['name' => 'Second Branch']);
        $drug = $this->drug();
        $this->batch($drug->id, $from->id, ['quantity_remaining' => 5]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock available at source branch for this transfer.');

        $this->transfer->transfer('drug', $drug->id, $from->id, $to->id, 10, 1);
    }
}
