<?php

namespace Database\Factories;

use App\Models\Server\Conversation;
use App\Models\Server\Profile;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Server\Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * @var class-string<\App\Models\Server\Conversation>
     */
    protected $model = Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requester_id' => User::factory(),
            'profile_id' => Profile::factory(),
            'request_id' => null,
            'status' => 'open',
            'last_message_at' => fake()->dateTimeBetween('-2 days'),
        ];
    }
}
