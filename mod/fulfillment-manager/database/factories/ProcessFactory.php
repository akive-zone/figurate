<?php

namespace Figurate\FulfillmentManager\Database\Factories;

use App\Models\Server\Profile;
use Figurate\FulfillmentManager\Models\Order;
use Figurate\FulfillmentManager\Models\Process;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Process>
 */
class ProcessFactory extends Factory
{
    /**
     * @var class-string<Process>
     */
    protected $model = Process::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'profile_id' => Profile::factory(),
            'type' => 'process.logged',
            'kind' => 'text',
            'content' => fake()->paragraph(),
        ];
    }
}
