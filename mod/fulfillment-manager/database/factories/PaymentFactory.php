<?php

namespace Figurate\FulfillmentManager\Database\Factories;

use Figurate\FulfillmentManager\Models\Order;
use Figurate\FulfillmentManager\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @var class-string<Payment>
     */
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'currency' => 'USD',
            'stage' => 'deposit',
            'status' => 'pending',
            'provider' => null,
            'provider_ref' => null,
        ];
    }
}
