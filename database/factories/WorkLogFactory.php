<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkLog>
 */
class WorkLogFactory extends Factory
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
            'profile_id' => Profile::factory(),
            'type' => 'text',
            'content' => fake()->paragraph(),
        ];
    }
}
