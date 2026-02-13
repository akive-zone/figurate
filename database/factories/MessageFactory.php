<?php

namespace Database\Factories;

use App\Models\Server\Message;
use App\Models\Server\Request;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Server\Message>
 */
class MessageFactory extends Factory
{
    /**
     * @var class-string<\App\Models\Server\Message>
     */
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'messageable_type' => Request::class,
            'messageable_id' => Request::factory(),
            'senderable_type' => User::class,
            'senderable_id' => User::factory(),
            'type' => 'text',
            'body' => fake()->paragraph(),
            'attachments' => null,
            'meta' => null,
        ];
    }
}
