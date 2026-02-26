<?php

namespace Database\Factories;

use App\Models\Server\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\\Models\\Server\\Store>
 */
class StoreFactory extends Factory
{
    /**
     * @var class-string<\App\Models\Server\Store>
     */
    protected $model = Store::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) fake()->uuid(),
            'name' => fake()->words(3, true),
            'provider' => 'default',
            'external_store_id' => null,
            'status' => 'active',
            'meta' => null,
        ];
    }
}
