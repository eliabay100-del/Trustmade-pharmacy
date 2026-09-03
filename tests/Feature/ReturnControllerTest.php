<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\IssuedItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PurchaseBatch;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed required accounts for accounting to work
        $this->artisan('db:seed', ['--class' => 'AccountSeeder']);
    }

    public function test_customer_return_index_displays_returns(): void
    {
        $user = User::factory()->create(['role' => 'pharmacist_clerk']);
        
        $response = $this->actingAs($user)->get(route('returns.index'));
        
        $response->assertStatus(200);
        $response->assertViewIs('returns.index');
        $response->assertViewHas('customerReturns');
        $response->assertViewHas('supplierReturns');
    }

    public function test_create_customer_return_shows_form(): void
    {
        $user = User::factory()->create(['role' => 'pharmacist_clerk']);
        
        $response = $this->actingAs($user)->get(route('returns.customer.create'));
        
        $response->assertStatus(200);
        $response->assertViewIs('returns.customer-create');
        $response->assertViewHas('orders');
        $response->assertViewHas('paymentMethods');
    }

    public function test_store_customer_return_successfully_records_return(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create([
            'role' => 'pharmacist_clerk',
            'branch_id' => $branch->id,
        ]);
        
        // Create category, drug, batch, order, and issued item
        $category = DrugCategory::factory()->create();
        $drug = Drug::factory()->create([
            'drug_category_id' => $category->id,
            'pack_size' => 10,
            'strip_size' => 10,
        ]);
        $batch = PurchaseBatch::factory()->create([
            'drug_id' => $drug->id,
            'branch_id' => $branch->id,
            'quantity_remaining' => 100,
        ]);
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'status' => 'completed',
        ]);
        $issuedItem = IssuedItem::factory()->create([
            'order_id' => $order->id,
            'purchase_batch_id' => $batch->id,
            'quantity' => 20,
            'quantity_unit' => 'tablet',
        ]);

        $response = $this->actingAs($user)->post(route('returns.customer.store'), [
            'issued_item_id' => $issuedItem->id,
            'quantity' => 5,
            'quantity_unit' => 'tablet',
            'refund_method' => 'cash',
            'notes' => 'Customer returned unused tablets',
        ]);

        $response->assertRedirect(route('returns.index'));
        $this->assertDatabaseHas('customer_returns', [
            'branch_id' => $branch->id,
            'refund_method' => 'cash',
        ]);
    }

    public function test_store_customer_return_validates_required_fields(): void
    {
        $user = User::factory()->create(['role' => 'pharmacist_clerk']);

        $response = $this->actingAs($user)->post(route('returns.customer.store'), []);

        $response->assertSessionHasErrors(['issued_item_id', 'quantity', 'quantity_unit', 'refund_method']);
    }

    public function test_store_customer_return_rejects_invalid_quantity_unit(): void
    {
        $user = User::factory()->create(['role' => 'pharmacist_clerk']);
        $item = IssuedItem::factory()->create();

        $response = $this->actingAs($user)->post(route('returns.customer.store'), [
            'issued_item_id' => $item->id,
            'quantity' => 5,
            'quantity_unit' => 'invalid_unit',
            'refund_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('quantity_unit');
    }

    public function test_store_customer_return_rejects_item_without_order(): void
    {
        $user = User::factory()->create(['role' => 'pharmacist_clerk']);
        $item = IssuedItem::factory()->create(['order_id' => null]);

        $response = $this->actingAs($user)->post(route('returns.customer.store'), [
            'issued_item_id' => $item->id,
            'quantity' => 5,
            'quantity_unit' => 'tablet',
            'refund_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('error');
    }

    public function test_create_supplier_return_shows_form(): void
    {
        $user = User::factory()->create(['role' => 'pharmacist_clerk']);
        
        $response = $this->actingAs($user)->get(route('returns.supplier.create'));
        
        $response->assertStatus(200);
        $response->assertViewIs('returns.supplier-create');
        $response->assertViewHas('batches');
    }

    public function test_store_supplier_return_successfully_records_return(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create([
            'role' => 'pharmacist_clerk',
            'branch_id' => $branch->id,
        ]);
        
        $category = DrugCategory::factory()->create();
        $drug = Drug::factory()->create([
            'drug_category_id' => $category->id,
            'pack_size' => 10,
            'strip_size' => 10,
        ]);
        $supplier = Supplier::factory()->create();
        $batch = PurchaseBatch::factory()->create([
            'drug_id' => $drug->id,
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'quantity_remaining' => 100,
        ]);

        $response = $this->actingAs($user)->post(route('returns.supplier.store'), [
            'purchase_batch_id' => $batch->id,
            'quantity' => 10,
            'quantity_unit' => 'tablet',
            'notes' => 'Expired batch return',
        ]);

        $response->assertRedirect(route('returns.index'));
        $this->assertDatabaseHas('supplier_returns', [
            'branch_id' => $branch->id,
            'purchase_batch_id' => $batch->id,
        ]);
    }

    public function test_store_supplier_return_validates_required_fields(): void
    {
        $user = User::factory()->create(['role' => 'pharmacist_clerk']);

        $response = $this->actingAs($user)->post(route('returns.supplier.store'), []);

        $response->assertSessionHasErrors(['purchase_batch_id', 'quantity', 'quantity_unit']);
    }

    public function test_branch_scope_prevents_access_to_other_branch_returns(): void
    {
        $branch1 = Branch::factory()->create();
        $branch2 = Branch::factory()->create();
        $user = User::factory()->create([
            'role' => 'pharmacist_clerk',
            'branch_id' => $branch1->id,
        ]);
        
        $category = DrugCategory::factory()->create();
        $drug = Drug::factory()->create(['drug_category_id' => $category->id, 'pack_size' => 10, 'strip_size' => 10]);
        $batch = PurchaseBatch::factory()->create([
            'drug_id' => $drug->id,
            'branch_id' => $branch2->id,
            'quantity_remaining' => 100,
        ]);

        // Try to return from batch in different branch
        $response = $this->actingAs($user)->post(route('returns.supplier.store'), [
            'purchase_batch_id' => $batch->id,
            'quantity' => 10,
            'quantity_unit' => 'tablet',
        ]);

        $response->assertStatus(403);
    }

    public function test_owner_admin_can_view_all_branches_returns(): void
    {
        $branch1 = Branch::factory()->create();
        $branch2 = Branch::factory()->create();
        $user = User::factory()->create([
            'role' => 'owner_admin',
            'branch_id' => null,
        ]);
        
        $return1 = CustomerReturn::factory()->create(['branch_id' => $branch1->id]);
        $return2 = CustomerReturn::factory()->create(['branch_id' => $branch2->id]);

        $response = $this->actingAs($user)->get(route('returns.index'));
        
        $response->assertStatus(200);
        // Owner admin should see returns from all branches
        $view = $response->original;
        $customerReturns = $view->getData('customerReturns');
        $this->assertGreaterThanOrEqual(2, $customerReturns->count());
    }

    public function test_store_customer_return_requires_valid_refund_method(): void
    {
        $user = User::factory()->create(['role' => 'pharmacist_clerk']);
        $item = IssuedItem::factory()->create();

        $response = $this->actingAs($user)->post(route('returns.customer.store'), [
            'issued_item_id' => $item->id,
            'quantity' => 5,
            'quantity_unit' => 'tablet',
            'refund_method' => 'invalid_method',
        ]);

        $response->assertSessionHasErrors('refund_method');
    }
}
