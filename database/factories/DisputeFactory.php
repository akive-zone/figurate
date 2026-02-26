<?php

namespace Database\Factories;

use App\Models\Server\Fulfillment\Dispute;
use App\Models\Server\Fulfillment\Order;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Server\Fulfillment\Dispute>
 */
class DisputeFactory extends Factory
{
    /**
     * @var class-string<\App\Models\Server\Fulfillment\Dispute>
     */
    protected $model = Dispute::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'dispute.opened',
            'status' => 'open',
            'payload' => [
                'reason' => fake()->sentence(),
                'resolved_at' => null,
            ],
            'meta' => [
                'order_id' => Order::factory(),
                'opened_by' => User::factory(),
                'resolved_by' => null,
            ],
            'occurred_at' => now(),
        ];
    }
}
