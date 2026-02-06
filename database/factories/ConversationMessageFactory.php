<?php

namespace Database\Factories;

use App\Models\Server\Conversation;
use App\Models\Server\ConversationMessage;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Server\ConversationMessage>
 */
class ConversationMessageFactory extends Factory
{
    /**
     * @var class-string<\App\Models\Server\ConversationMessage>
     */
    protected $model = ConversationMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => User::factory(),
            'type' => 'text',
            'body' => fake()->paragraph(),
            'attachments' => null,
            'meta' => null,
        ];
    }
}
