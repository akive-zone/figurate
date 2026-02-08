<?php

namespace Database\Factories;

use App\Models\Server\Order;
use App\Models\Server\Process;
use App\Models\Server\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Server\Process>
 */
class ProcessFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Process::class;

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
