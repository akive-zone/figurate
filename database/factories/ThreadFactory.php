<?php

namespace Database\Factories;

use App\Models\Server\Request;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Server\Thread>
 */
class ThreadFactory extends Factory
{
    /**
     * @var class-string<\App\Models\Server\Thread>
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
            'threadable_type' => Request::class,
            'threadable_id' => Request::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(3),
            'phase' => 'request_intake',
            'status' => 'open',
        ];
    }
}
