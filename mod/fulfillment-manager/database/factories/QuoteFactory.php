<?php

namespace Figurate\FulfillmentManager\Database\Factories;

use App\Models\Server\Profile;
use Figurate\FulfillmentManager\Models\Quote;
use Figurate\FulfillmentManager\Models\Request;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * @var class-string<Quote>
     */
    protected $model = Quote::class;

    /**
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
