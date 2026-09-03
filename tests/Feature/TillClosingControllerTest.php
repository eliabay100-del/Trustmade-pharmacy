<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CustomerReturn;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TillClosingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed required accounts for accounting to work
        $this->artisan('db:seed', ['--class' => 'AccountSeeder']);
    }

    public function test_index_displays_till_closing_page(): void
    {
        $user = User::factory()->create(['role' => 'pharmacist_clerk']);
        
        $response = $this->actingAs($user)->get(route('till-closings.index'));
        
        $response->assertStatus(200);
        $response->assertViewIs('till-closings.index');
        $response->assertViewHas('periodStart');
        $response->assertViewHas('expectedCash');
        $response->assertViewHas('otherPayments');
    }

    public function test_store_creates_till_closing_successfully(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create([
            'role' => 'pharmacist_clerk',
            'branch_id' => $branch->id,
        ]);

        // Create a cash payment to have expected cash
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'status' => 'completed',
        ]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 100.00,
        ]);

        $response = $this->actingAs($user)->post(route('till-closings.store'), [
            'counted_cash' => 100.00,
            'notes' => 'Test closing',
        ]);

        $response->assertRedirect(route('till-closings.index'));
        $this->assertDatabaseHas('till_closings', [
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'counted_cash' => 100.00,
            'variance' => 0.00,
        ]);
    }

    public function test_store_calculates_variance_correctly(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create([
            'role' => 'pharmacist_clerk',
            'branch_id' => $branch->id,
        ]);

        // Create cash payment of 50
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'status' => 'completed',
        ]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 50.00,
        ]);

        // Count only 45 (short by 5)
        $response = $this->actingAs($user)->post(route('till-closings.store'), [
            'counted_cash' => 45.00,
            'notes' => 'Short by 5',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('till_closings', [
            'user_id' => $user->id,
            'expected_cash' => 50.00,
            'counted_cash' => 45.00,
            'variance' => -5.00,
        ]);
    }

    public function test_store_requires_counted_cash(): void
    {
        $user = User::factory()->create(['role' => 'pharmacist_clerk']);

        $response = $this->actingAs($user)->post(route('till-closings.store'), [
            'notes' => 'No count provided',
        ]);

        $response->assertSessionHasErrors('counted_cash');
    }

    public function test_store_validates_counted_cash_is_positive(): void
    {
        $user = User::factory()->create(['role' => 'pharmacist_clerk']);

        $response = $this->actingAs($user)->post(route('till-closings.store'), [
            'counted_cash' => -10,
        ]);

        $response->assertSessionHasErrors('counted_cash');
    }

    public function test_index_excludes_previous_period_from_calculation(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create([
            'role' => 'pharmacist_clerk',
            'branch_id' => $branch->id,
        ]);

        // Create a previous till closing
        $previousClose = \App\Models\TillClosing::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'closed_at' => now()->subDay(),
        ]);

        // Create payments before and after the previous close
        $order1 = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'created_at' => $previousClose->closed_at->subHour(),
        ]);
        Payment::factory()->create([
            'order_id' => $order1->id,
            'payment_method' => 'cash',
            'amount' => 100.00,
            'created_at' => $previousClose->closed_at->subHour(),
        ]);

        $order2 = Order::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'created_at' => $previousClose->closed_at->addHour(),
        ]);
        Payment::factory()->create([
            'order_id' => $order2->id,
            'payment_method' => 'cash',
            'amount' => 75.00,
            'created_at' => $previousClose->closed_at->addHour(),
        ]);

        $response = $this->actingAs($user)->get(route('till-closings.index'));
        
        $response->assertStatus(200);
        // Should only include the 75 from after the previous close, not the 100 from before
        $view = $response->original;
        $this->assertEquals(75.00, $view->getData('expectedCash'));
    }
}
