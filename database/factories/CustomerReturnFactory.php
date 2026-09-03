<?php

namespace Database\Factories;

use App\Models\CustomerReturn;
use App\Models\Order;
use App\Models\IssuedItem;
use App\Models\PurchaseBatch;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerReturn>
 */
class CustomerReturnFactory extends Factory
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
            'issued_item_id' => IssuedItem::factory(),
            'purchase_batch_id' => PurchaseBatch::factory(),
            'branch_id' => Branch::factory(),
            'user_id' => User::factory(),
            'quantity' => fake()->numberBetween(1, 20),
            'unit_cost' => fake()->randomFloat(2, 1, 50),
            'cost_amount' => fake()->randomFloat(2, 5, 100),
            'refund_amount' => fake()->randomFloat(2, 5, 150),
            'refund_method' => 'cash',
            'notes' => null,
        ];
    }

    /**
     * Set refund method to cash.
     */
    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'refund_method' => 'cash',
        ]);
    }

    /**
     * Set refund method to telebirr.
     */
    public function telebirr(): static
    {
        return $this->state(fn (array $attributes) => [
            'refund_method' => 'telebirr',
        ]);
    }
}
