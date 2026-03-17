<?php

namespace Figurate\FulfillmentManager\Database\Factories;

use Figurate\FulfillmentManager\Models\Request;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Request>
 */
class RequestFactory extends Factory
{
    /**
     * @var class-string<Request>
     */
    protected $model = Request::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'request.created',
            'status' => 'open',
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'occurred_at' => now(),
        ];
    }
}
