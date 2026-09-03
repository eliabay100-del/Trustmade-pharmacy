<?php

namespace Database\Factories;

use App\Models\SupplierReturn;
use App\Models\PurchaseBatch;
use App\Models\Supplier;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierReturn>
 */
class SupplierReturnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_batch_id' => PurchaseBatch::factory(),
            'supplier_id' => Supplier::factory(),
            'branch_id' => Branch::factory(),
            'user_id' => User::factory(),
            'quantity' => fake()->numberBetween(1, 50),
            'unit_cost' => fake()->randomFloat(2, 1, 50),
            'total_amount' => fake()->randomFloat(2, 10, 500),
            'notes' => null,
        ];
    }
}
