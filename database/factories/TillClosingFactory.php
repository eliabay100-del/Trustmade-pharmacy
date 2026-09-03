<?php

namespace Database\Factories;

use App\Models\TillClosing;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TillClosing>
 */
class TillClosingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'branch_id' => Branch::factory(),
            'period_start' => now()->subDay(),
            'closed_at' => now(),
            'expected_cash' => fake()->randomFloat(2, 50, 500),
            'counted_cash' => fake()->randomFloat(2, 50, 500),
            'variance' => 0.00,
            'notes' => null,
        ];
    }

    /**
     * Set variance to a specific value.
     */
    public function withVariance(float $variance): static
    {
        return $this->state(fn (array $attributes) => [
            'variance' => $variance,
        ]);
    }

    /**
     * Create a till closing with no variance (balanced).
     */
    public function balanced(): static
    {
        return $this->afterMaking(function (TillClosing $closing) {
            $closing->counted_cash = $closing->expected_cash;
            $closing->variance = 0.00;
        });
    }
}
