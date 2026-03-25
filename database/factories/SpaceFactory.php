<?php

namespace Database\Factories;

use App\Models\Server\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Space>
 */
class SpaceFactory extends Factory
{
    /**
     * @var class-string<Space>
     */
    protected $model = Space::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => 'open',
        ];
    }
}
