<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\PurchaseBatch;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;
    protected Drug $drug;
    protected PurchaseBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->branch = Branch::create(['name' => 'Main Branch']);
        $this->user = User::create([
            'name' => 'Test Pharmacist',
            'email' => 'pharmacist@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PHARMACIST_CLERK,
            'branch_id' => $this->branch->id,
        ]);

        // Create a drug and batch for adjustments
        $this->drug = Drug::create([
            'name' => 'Paracetamol 500mg',
            'generic_name' => 'Paracetamol',
            'strength' => '500mg',
            'form' => 'Tablet',
            'pack_size' => 10,
            'strip_size' => 10,
            'selling_price' => 1.00,
            'purchase_price' => 0.50,
        ]);

        $this->batch = PurchaseBatch::create([
            'drug_id' => $this->drug->id,
            'branch_id' => $this->branch->id,
            'quantity' => 100,
            'quantity_remaining' => 100,
            'expiry_date' => now()->addYear(),
            'batch_number' => 'BATCH001',
            'purchase_price' => 0.50,
            'selling_price' => 1.00,
        ]);
    }

    public function test_user_can_view_adjustments_index_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('adjustments.index'));

        $response->assertStatus(200);
        $response->assertViewIs('adjustments.index');
    }

    public function test_user_can_view_create_adjustment_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('adjustments.create'));

        $response->assertStatus(200);
        $response->assertViewIs('adjustments.create');
        $response->assertViewHas('batches');
        $response->assertViewHas('reasons');
    }

    public function test_user_can_store_stock_adjustment_write_off(): void
    {
        $response = $this->actingAs($this->user)->post(route('adjustments.store'), [
            'purchase_batch_id' => $this->batch->id,
            'quantity' => 10,
            'quantity_unit' => 'tablet',
            'reason' => 'expired',
            'notes' => 'Expired stock write-off',
        ]);

        $response->assertRedirect(route('adjustments.index'));
        $this->assertDatabaseHas('stock_adjustments', [
            'branch_id' => $this->branch->id,
            'purchase_batch_id' => $this->batch->id,
            'quantity' => 10,
            'reason' => 'expired',
        ]);
    }

    public function test_stock_adjustment_requires_valid_batch(): void
    {
        $response = $this->actingAs($this->user)->post(route('adjustments.store'), [
            'purchase_batch_id' => 99999,
            'quantity' => 10,
            'quantity_unit' => 'tablet',
            'reason' => 'expired',
        ]);

        $response->assertSessionHasErrors('purchase_batch_id');
    }

    public function test_stock_adjustment_requires_valid_quantity(): void
    {
        $response = $this->actingAs($this->user)->post(route('adjustments.store'), [
            'purchase_batch_id' => $this->batch->id,
            'quantity' => 0,
            'quantity_unit' => 'tablet',
            'reason' => 'expired',
        ]);

        $response->assertSessionHasErrors('quantity');
    }

    public function test_stock_adjustment_requires_valid_reason(): void
    {
        $response = $this->actingAs($this->user)->post(route('adjustments.store'), [
            'purchase_batch_id' => $this->batch->id,
            'quantity' => 10,
            'quantity_unit' => 'tablet',
            'reason' => 'invalid_reason',
        ]);

        $response->assertSessionHasErrors('reason');
    }

    public function test_stock_adjustment_requires_notes_for_other_reason(): void
    {
        $response = $this->actingAs($this->user)->post(route('adjustments.store'), [
            'purchase_batch_id' => $this->batch->id,
            'quantity' => 10,
            'quantity_unit' => 'tablet',
            'reason' => 'other',
            'notes' => '',
        ]);

        $response->assertSessionHasErrors('notes');
    }

    public function test_user_cannot_adjust_batch_from_different_branch(): void
    {
        $otherBranch = Branch::create(['name' => 'Other Branch']);
        
        $otherBatch = PurchaseBatch::create([
            'drug_id' => $this->drug->id,
            'branch_id' => $otherBranch->id,
            'quantity' => 100,
            'quantity_remaining' => 100,
            'expiry_date' => now()->addYear(),
            'batch_number' => 'BATCH002',
            'purchase_price' => 0.50,
            'selling_price' => 1.00,
        ]);

        $response = $this->actingAs($this->user)->post(route('adjustments.store'), [
            'purchase_batch_id' => $otherBatch->id,
            'quantity' => 10,
            'quantity_unit' => 'tablet',
            'reason' => 'expired',
        ]);

        $response->assertStatus(403);
    }

    public function test_adjustment_filters_batches_by_branch(): void
    {
        $otherBranch = Branch::create(['name' => 'Other Branch']);
        
        PurchaseBatch::create([
            'drug_id' => $this->drug->id,
            'branch_id' => $otherBranch->id,
            'quantity' => 100,
            'quantity_remaining' => 100,
            'expiry_date' => now()->addYear(),
            'batch_number' => 'BATCH002',
            'purchase_price' => 0.50,
            'selling_price' => 1.00,
        ]);

        $response = $this->actingAs($this->user)->get(route('adjustments.create'));

        $response->assertStatus(200);
        // Should only see batches from own branch
        $batches = $response->viewData('batches');
        foreach ($batches as $batch) {
            $this->assertEquals($this->branch->id, $batch->branch_id);
        }
    }

    public function test_only_batches_with_remaining_stock_are_shown(): void
    {
        // Create a batch with zero remaining stock
        PurchaseBatch::create([
            'drug_id' => $this->drug->id,
            'branch_id' => $this->branch->id,
            'quantity' => 100,
            'quantity_remaining' => 0,
            'expiry_date' => now()->addYear(),
            'batch_number' => 'BATCH002',
            'purchase_price' => 0.50,
            'selling_price' => 1.00,
        ]);

        $response = $this->actingAs($this->user)->get(route('adjustments.create'));

        $response->assertStatus(200);
        $batches = $response->viewData('batches');
        foreach ($batches as $batch) {
            $this->assertGreaterThan(0, $batch->quantity_remaining);
        }
    }

    public function test_owner_admin_can_see_all_adjustments(): void
    {
        $otherBranch = Branch::create(['name' => 'Other Branch']);
        
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_OWNER_ADMIN,
            'branch_id' => null,
        ]);

        $otherAdjustment = StockAdjustment::create([
            'branch_id' => $otherBranch->id,
            'purchase_batch_id' => $this->batch->id,
            'quantity' => 5,
            'reason' => 'damaged',
            'user_id' => $adminUser->id,
            'total_amount' => 2.50,
        ]);

        $response = $this->actingAs($adminUser)->get(route('adjustments.index'));

        $response->assertStatus(200);
        // Admin should see all adjustments including from other branches
        $adjustments = $response->viewData('adjustments');
        $found = false;
        foreach ($adjustments as $adj) {
            if ($adj->id === $otherAdjustment->id) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Admin should see adjustments from all branches');
    }

    public function test_pharmacist_clerk_only_sees_own_branch_adjustments(): void
    {
        $otherBranch = Branch::create(['name' => 'Other Branch']);
        
        $otherAdjustment = StockAdjustment::create([
            'branch_id' => $otherBranch->id,
            'purchase_batch_id' => $this->batch->id,
            'quantity' => 5,
            'reason' => 'damaged',
            'user_id' => $this->user->id,
            'total_amount' => 2.50,
        ]);

        $response = $this->actingAs($this->user)->get(route('adjustments.index'));

        $response->assertStatus(200);
        // Clerk should only see adjustments from own branch
        $adjustments = $response->viewData('adjustments');
        foreach ($adjustments as $adj) {
            $this->assertEquals($this->branch->id, $adj->branch_id);
        }
    }
}
