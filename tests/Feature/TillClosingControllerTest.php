<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CustomerReturn;
use App\Models\IssuedItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PurchaseBatch;
use App\Models\StockAdjustment;
use App\Models\SupplierReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TillClosingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->branch = Branch::create(['name' => 'Main Branch']);
        $this->user = User::create([
            'name' => 'Test Cashier',
            'email' => 'cashier@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_PHARMACIST_CLERK,
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_user_can_view_till_closings_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('till-closings.index'));

        $response->assertStatus(200);
        $response->assertViewIs('till-closings.index');
    }

    public function test_user_can_close_till_with_correct_cash_count(): void
    {
        // Create a completed order with cash payment for this user's branch
        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => null,
            'user_id' => $this->user->id,
            'total_amount' => 100.00,
            'status' => 'completed',
        ]);

        Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 100.00,
        ]);

        $response = $this->actingAs($this->user)->post(route('till-closings.store'), [
            'counted_cash' => 100.00,
            'notes' => 'End of shift close',
        ]);

        $response->assertRedirect(route('till-closings.index'));
        $this->assertDatabaseHas('till_closings', [
            'user_id' => $this->user->id,
            'branch_id' => $this->branch->id,
            'expected_cash' => 100.00,
            'counted_cash' => 100.00,
            'variance' => 0.00,
        ]);
    }

    public function test_user_cannot_close_till_without_branch(): void
    {
        // Create an owner_admin user without branch assignment
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_OWNER_ADMIN,
            'branch_id' => null,
        ]);

        $response = $this->actingAs($adminUser)->post(route('till-closings.store'), [
            'counted_cash' => 100.00,
            'branch_id' => '', // No branch selected
        ]);

        $response->assertSessionHasErrors('branch_id');
    }

    public function test_till_closing_calculates_variance_correctly(): void
    {
        // Create orders with cash payments
        $order1 = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => null,
            'user_id' => $this->user->id,
            'total_amount' => 50.00,
            'status' => 'completed',
        ]);

        Payment::create([
            'order_id' => $order1->id,
            'payment_method' => 'cash',
            'amount' => 50.00,
        ]);

        $order2 = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => null,
            'user_id' => $this->user->id,
            'total_amount' => 75.00,
            'status' => 'completed',
        ]);

        Payment::create([
            'order_id' => $order2->id,
            'payment_method' => 'cash',
            'amount' => 75.00,
        ]);

        // Count less than expected (shortage)
        $response = $this->actingAs($this->user)->post(route('till-closings.store'), [
            'counted_cash' => 120.00,
            'notes' => 'Short by 5',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('till_closings', [
            'user_id' => $this->user->id,
            'expected_cash' => 125.00,
            'counted_cash' => 120.00,
            'variance' => -5.00,
        ]);
    }

    public function test_till_closing_excludes_previous_period_payments(): void
    {
        // Create a previous till closing
        $previousClose = \App\Models\TillClosing::create([
            'user_id' => $this->user->id,
            'branch_id' => $this->branch->id,
            'period_start' => now()->subDay(),
            'closed_at' => now()->subHours(2),
            'expected_cash' => 0.00,
            'counted_cash' => 0.00,
            'variance' => 0.00,
        ]);

        // Create order BEFORE the last close (should be excluded)
        $oldOrder = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => null,
            'user_id' => $this->user->id,
            'total_amount' => 200.00,
            'status' => 'completed',
            'created_at' => $previousClose->closed_at->subMinute(),
        ]);

        Payment::create([
            'order_id' => $oldOrder->id,
            'payment_method' => 'cash',
            'amount' => 200.00,
            'created_at' => $previousClose->closed_at->subMinute(),
        ]);

        // Create order AFTER the last close (should be included)
        $newOrder = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => null,
            'user_id' => $this->user->id,
            'total_amount' => 50.00,
            'status' => 'completed',
            'created_at' => $previousClose->closed_at->addMinute(),
        ]);

        Payment::create([
            'order_id' => $newOrder->id,
            'payment_method' => 'cash',
            'amount' => 50.00,
            'created_at' => $previousClose->closed_at->addMinute(),
        ]);

        $response = $this->actingAs($this->user)->post(route('till-closings.store'), [
            'counted_cash' => 50.00,
            'notes' => 'Only new period cash',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('till_closings', [
            'user_id' => $this->user->id,
            'expected_cash' => 50.00, // Only the new order, not the old 200
            'counted_cash' => 50.00,
            'variance' => 0.00,
        ]);
    }

    public function test_non_cash_payments_are_tracked_separately(): void
    {
        $order = Order::create([
            'branch_id' => $this->branch->id,
            'customer_id' => null,
            'user_id' => $this->user->id,
            'total_amount' => 100.00,
            'status' => 'completed',
        ]);

        Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'telebirr',
            'amount' => 100.00,
        ]);

        $response = $this->actingAs($this->user)->get(route('till-closings.index'));

        $response->assertStatus(200);
        // Expected cash should be 0 since payment was via telebirr
        $response->assertViewHas('expectedCash', 0.00);
    }
}
