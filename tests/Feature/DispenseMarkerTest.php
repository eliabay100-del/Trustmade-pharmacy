<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\IssuedItem;
use App\Models\PurchaseBatch;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dispensing marker on the sale screen ("#1 - 40 left") and the FEFO/FIFO
 * order it promises.
 *
 * The marker is recomputed from the batches on every page load (nothing is
 * stored), and its ORDER BY is deliberately identical to
 * InventoryService::selectBatchesForDispensing(), so the batch the seller is
 * told about is always the batch that is actually consumed.
 */
class DispenseMarkerTest extends TestCase
{
    use RefreshDatabase;

    /** The exact string the sale screen renders next to an item name. */
    private const MARKER = '#1 · %d left';

    protected User $clerk;
    protected Branch $branch;
    protected Supplier $supplier;
    protected Drug $drug;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountSeeder::class);

        $this->branch = Branch::create(['name' => 'Main Branch']);
        $this->supplier = Supplier::create(['name' => 'Medi Supply']);
        $this->drug = Drug::create([
            'name' => 'Paracetamol',
            'strength' => '500mg',
            'category_id' => DrugCategory::create(['name' => 'Pain relief'])->id,
        ]);
        $this->clerk = User::create([
            'name' => 'Clerk',
            'username' => 'clerk',
            'email' => 'clerk@example.com',
            'password' => 'secret1234',
            'role' => 'pharmacist_clerk',
            'branch_id' => $this->branch->id,
        ]);
    }

    protected function batch(string $batchNumber, string $expiry, int $remaining): PurchaseBatch
    {
        return PurchaseBatch::create([
            'drug_id' => $this->drug->id,
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'batch_number' => $batchNumber,
            'quantity_received' => $remaining,
            'quantity_remaining' => $remaining,
            'cost_per_unit' => 10,
            'selling_price_per_unit' => 15,
            'expiry_date' => $expiry,
            'purchase_date' => now()->toDateString(),
        ]);
    }

    public function test_marker_shows_the_earliest_expiring_batch_and_its_quantity(): void
    {
        $this->batch('SOON', now()->addDays(30)->toDateString(), 40);
        $this->batch('LATER', now()->addDays(300)->toDateString(), 100);

        $response = $this->actingAs($this->clerk)->get(route('dispense.create'));

        $response->assertOk();
        $response->assertSee(sprintf(self::MARKER, 40), false);
        $response->assertDontSee('100 left', false);
    }

    /**
     * The original problem: three batches bought the same day with the same
     * expiry date. The oldest row must be consumed first, and the marker must
     * say so.
     */
    public function test_same_day_same_expiry_batches_dispense_oldest_first(): void
    {
        $expiry = now()->addYear()->toDateString();
        $first = $this->batch('ONE', $expiry, 10);
        $second = $this->batch('TWO', $expiry, 20);
        $third = $this->batch('THREE', $expiry, 30);

        $this->actingAs($this->clerk)->get(route('dispense.create'))
            ->assertOk()
            ->assertSee(sprintf(self::MARKER, 10), false);

        $this->actingAs($this->clerk)->post(route('dispense.store'), [
            'customer_mode' => 'anonymous',
            'items' => [[
                'drug_id' => 'drug:' . $this->drug->id,
                'quantity' => 3,
                'unit' => 'tablet',
                'selling_price' => 15,
            ]],
            'payments' => [['payment_method' => 'cash', 'amount' => 45]],
        ])->assertRedirect();

        $issued = IssuedItem::first();
        $this->assertSame($first->id, $issued->purchase_batch_id);
        $this->assertSame(7, (int) $first->fresh()->quantity_remaining);
        $this->assertSame(20, (int) $second->fresh()->quantity_remaining);
        $this->assertSame(30, (int) $third->fresh()->quantity_remaining);
    }

    public function test_marker_is_omitted_when_the_item_has_no_usable_stock(): void
    {
        $this->batch('GONE', now()->addYear()->toDateString(), 0);

        $this->actingAs($this->clerk)->get(route('dispense.create'))
            ->assertOk()
            // No marker prefix at all, rather than a misleading "#1 · 0 left".
            ->assertDontSee('#1 · ', false);
    }

    public function test_marker_ignores_expired_batches(): void
    {
        $this->batch('DEAD', now()->subDay()->toDateString(), 999);
        $this->batch('LIVE', now()->addYear()->toDateString(), 7);

        $this->actingAs($this->clerk)->get(route('dispense.create'))
            ->assertOk()
            ->assertSee(sprintf(self::MARKER, 7), false)
            ->assertDontSee('999 left', false);
    }

    public function test_marker_recomputes_when_the_first_batch_is_used_up(): void
    {
        $expiry = now()->addYear()->toDateString();
        $first = $this->batch('ONE', $expiry, 2);
        $this->batch('TWO', $expiry, 50);

        $this->actingAs($this->clerk)->get(route('dispense.create'))
            ->assertSee(sprintf(self::MARKER, 2), false);

        // Sell the first batch dry, then reload: the marker must move to #1 on
        // what is now the next batch out, with no stored state to renumber.
        $first->update(['quantity_remaining' => 0]);

        $this->actingAs($this->clerk)->get(route('dispense.create'))
            ->assertSee(sprintf(self::MARKER, 50), false);
    }
}
