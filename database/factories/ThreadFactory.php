<?php

namespace Database\Factories;

use App\Models\Server\Space;
use App\Models\Server\Thread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Thread>
 */
class ThreadFactory extends Factory
{
    /**
     * @var class-string<Thread>
     */
    protected $model = Thread::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'threadable_type' => Space::class,
            'threadable_id' => Space::factory(),
            'purpose' => Thread::PurposeMain,
            'title' => fake()->sentence(3),
            'phase' => 'conversation_open',
            'status' => 'open',
        ];
    }
}
