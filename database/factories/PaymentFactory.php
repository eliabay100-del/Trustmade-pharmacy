<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'payment_method' => array_rand(array_keys(Payment::METHODS)),
            'amount' => fake()->randomFloat(2, 5, 200),
        ];
    }

    /**
     * Set payment method to cash.
     */
    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'cash',
        ]);
    }

    /**
     * Set payment method to telebirr.
     */
    public function telebirr(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'telebirr',
        ]);
    }

    /**
     * Set payment method to card.
     */
    public function card(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'card',
        ]);
    }
}
