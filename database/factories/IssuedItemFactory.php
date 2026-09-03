<?php

namespace Database\Factories;

use App\Models\IssuedItem;
use App\Models\Order;
use App\Models\PurchaseBatch;
use App\Models\Customer;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IssuedItem>
 */
class IssuedItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'purchase_batch_id' => PurchaseBatch::factory(),
            'customer_id' => Customer::factory(),
            'branch_id' => Branch::factory(),
            'quantity_issued' => fake()->numberBetween(1, 50),
            'selling_price_per_unit' => fake()->randomFloat(2, 1, 100),
            'total_amount' => fake()->randomFloat(2, 10, 500),
            'issue_date' => now(),
        ];
    }

    /**
     * Set a specific quantity issued.
     */
    public function withQuantity(int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_issued' => $quantity,
        ]);
    }
}
