<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\Request;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => Request::factory(),
            'profile_id' => Profile::factory(),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'currency' => 'USD',
            'details' => fake()->paragraph(),
            'status' => 'pending',
        ];
    }
}
