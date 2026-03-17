<?php

namespace Figurate\FulfillmentManager\Database\Factories;

use App\Models\Server\User;
use Figurate\FulfillmentManager\Models\Order;
use Figurate\FulfillmentManager\Models\Rating;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rating>
 */
class RatingFactory extends Factory
{
    /**
     * @var class-string<Rating>
     */
    protected $model = Rating::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'rater_id' => User::factory(),
            'rated_id' => User::factory(),
            'score' => fake()->numberBetween(1, 5),
            'comment' => fake()->sentence(),
        ];
    }
}
