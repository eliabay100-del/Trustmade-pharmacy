<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CustomerReturn;
use App\Models\Drug;
use App\Models\IssuedItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PurchaseBatch;
use App\Models\SupplierReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnControllerTest extends TestCase
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

        // Create a drug and batch for returns
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

    public function test_user_can_view_returns_index_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('returns.index'));

        $response->assertStatus(200);
        $response->assertViewIs('returns.index');
    }

    public function test_user_can_view_customer_return_create_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('returns.customer.create'));

        $response->assertStatus(200);
        $response->assertViewIs('returns.customer-create');
        $response->assertViewHas('orders');
        $response->assertViewHas('paymentMethods');
    }

    public function test_user_can_view_customer_return_create_page_with_order(): void
    {
        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => null,
            'user_id' => $this->user->id,
            'total_amount' => 50.00,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)->get(route('returns.customer.create', ['order_id' => $order->id]));

        $response->assertStatus(200);
        $response->assertViewIs('returns.customer-create');
        $response->assertViewHas('order', function ($viewOrder) use ($order) {
            return $viewOrder->id === $order->id;
        });
    }

    public function test_user_cannot_access_order_from_different_branch(): void
    {
        $otherBranch = Branch::create(['name' => 'Other Branch']);
        $order = Order::create([
            'branch_id' => $otherBranch->id,
            'customer_id' => null,
            'user_id' => $this->user->id,
            'total_amount' => 50.00,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)->get(route('returns.customer.create', ['order_id' => $order->id]));

        $response->assertStatus(403);
    }

    public function test_user_can_store_customer_return(): void
    {
        // Create an order with an issued item
        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => null,
            'user_id' => $this->user->id,
            'total_amount' => 50.00,
            'status' => 'completed',
        ]);

        $issuedItem = IssuedItem::create([
            'order_id' => $order->id,
            'branch_id' => $this->branch->id,
            'purchase_batch_id' => $this->batch->id,
            'quantity' => 10,
            'quantity_unit' => 'tablet',
            'unit_price' => 1.00,
            'total_price' => 10.00,
        ]);

        $response = $this->actingAs($this->user)->post(route('returns.customer.store'), [
            'issued_item_id' => $issuedItem->id,
            'quantity' => 5,
            'quantity_unit' => 'tablet',
            'refund_method' => 'cash',
            'notes' => 'Customer returned unused tablets',
        ]);

        $response->assertRedirect(route('returns.index'));
        $this->assertDatabaseHas('customer_returns', [
            'branch_id' => $this->branch->id,
            'issued_item_id' => $issuedItem->id,
            'quantity' => 5,
            'refund_method' => 'cash',
        ]);
    }

    public function test_customer_return_requires_valid_issued_item(): void
    {
        $response = $this->actingAs($this->user)->post(route('returns.customer.store'), [
            'issued_item_id' => 99999,
            'quantity' => 5,
            'quantity_unit' => 'tablet',
            'refund_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('issued_item_id');
    }

    public function test_customer_return_requires_valid_quantity(): void
    {
        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => null,
            'user_id' => $this->user->id,
            'total_amount' => 50.00,
            'status' => 'completed',
        ]);

        $issuedItem = IssuedItem::create([
            'order_id' => $order->id,
            'branch_id' => $this->branch->id,
            'purchase_batch_id' => $this->batch->id,
            'quantity' => 10,
            'quantity_unit' => 'tablet',
            'unit_price' => 1.00,
            'total_price' => 10.00,
        ]);

        $response = $this->actingAs($this->user)->post(route('returns.customer.store'), [
            'issued_item_id' => $issuedItem->id,
            'quantity' => 0,
            'quantity_unit' => 'tablet',
            'refund_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('quantity');
    }

    public function test_customer_return_requires_valid_refund_method(): void
    {
        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => null,
            'user_id' => $this->user->id,
            'total_amount' => 50.00,
            'status' => 'completed',
        ]);

        $issuedItem = IssuedItem::create([
            'order_id' => $order->id,
            'branch_id' => $this->branch->id,
            'purchase_batch_id' => $this->batch->id,
            'quantity' => 10,
            'quantity_unit' => 'tablet',
            'unit_price' => 1.00,
            'total_price' => 10.00,
        ]);

        $response = $this->actingAs($this->user)->post(route('returns.customer.store'), [
            'issued_item_id' => $issuedItem->id,
            'quantity' => 5,
            'quantity_unit' => 'tablet',
            'refund_method' => 'invalid_method',
        ]);

        $response->assertSessionHasErrors('refund_method');
    }

    public function test_user_cannot_return_item_from_different_branch(): void
    {
        $otherBranch = Branch::create(['name' => 'Other Branch']);
        
        $order = Order::create([
            'branch_id' => $otherBranch->id,
            'customer_id' => null,
            'user_id' => $this->user->id,
            'total_amount' => 50.00,
            'status' => 'completed',
        ]);

        $issuedItem = IssuedItem::create([
            'order_id' => $order->id,
            'branch_id' => $otherBranch->id,
            'purchase_batch_id' => $this->batch->id,
            'quantity' => 10,
            'quantity_unit' => 'tablet',
            'unit_price' => 1.00,
            'total_price' => 10.00,
        ]);

        $response = $this->actingAs($this->user)->post(route('returns.customer.store'), [
            'issued_item_id' => $issuedItem->id,
            'quantity' => 5,
            'quantity_unit' => 'tablet',
            'refund_method' => 'cash',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_view_supplier_return_create_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('returns.supplier.create'));

        $response->assertStatus(200);
        $response->assertViewIs('returns.supplier-create');
        $response->assertViewHas('batches');
    }

    public function test_user_can_store_supplier_return(): void
    {
        $response = $this->actingAs($this->user)->post(route('returns.supplier.store'), [
            'purchase_batch_id' => $this->batch->id,
            'quantity' => 10,
            'quantity_unit' => 'tablet',
            'notes' => 'Returning damaged stock to supplier',
        ]);

        $response->assertRedirect(route('returns.index'));
        $this->assertDatabaseHas('supplier_returns', [
            'branch_id' => $this->branch->id,
            'purchase_batch_id' => $this->batch->id,
            'quantity' => 10,
        ]);
    }

    public function test_supplier_return_requires_valid_batch(): void
    {
        $response = $this->actingAs($this->user)->post(route('returns.supplier.store'), [
            'purchase_batch_id' => 99999,
            'quantity' => 10,
            'quantity_unit' => 'tablet',
        ]);

        $response->assertSessionHasErrors('purchase_batch_id');
    }

    public function test_user_cannot_return_batch_from_different_branch(): void
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

        $response = $this->actingAs($this->user)->post(route('returns.supplier.store'), [
            'purchase_batch_id' => $otherBatch->id,
            'quantity' => 10,
            'quantity_unit' => 'tablet',
        ]);

        $response->assertStatus(403);
    }

    public function test_supplier_return_filters_batches_by_branch(): void
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

        $response = $this->actingAs($this->user)->get(route('returns.supplier.create'));

        $response->assertStatus(200);
        // Should only see batches from own branch
        $batches = $response->viewData('batches');
        foreach ($batches as $batch) {
            $this->assertEquals($this->branch->id, $batch->branch_id);
        }
    }
}
