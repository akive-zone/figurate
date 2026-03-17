<?php

namespace Figurate\FulfillmentManager\Database\Factories;

use Figurate\FulfillmentManager\Models\Assessment;
use Figurate\FulfillmentManager\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    /**
     * @var class-string<Assessment>
     */
    protected $model = Assessment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'notes' => fake()->paragraph(),
            'status' => 'pending_ack',
            'acknowledged_at' => null,
        ];
    }
}
