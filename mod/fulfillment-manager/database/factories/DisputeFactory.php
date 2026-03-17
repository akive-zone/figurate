<?php

namespace Figurate\FulfillmentManager\Database\Factories;

use App\Models\Server\User;
use Figurate\FulfillmentManager\Models\Dispute;
use Figurate\FulfillmentManager\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dispute>
 */
class DisputeFactory extends Factory
{
    /**
     * @var class-string<Dispute>
     */
    protected $model = Dispute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'dispute.opened',
            'status' => 'open',
            'order_id' => Order::factory(),
            'opened_by' => User::factory(),
            'resolved_by' => null,
            'reason' => fake()->sentence(),
            'resolved_at' => null,
            'occurred_at' => now(),
        ];
    }
}
