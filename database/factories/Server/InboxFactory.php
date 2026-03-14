<?php

namespace Database\Factories\Server;

use App\Models\Server\Inbox;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Server\Inbox>
 */
class InboxFactory extends Factory
{
    /**
     * @var class-string<\App\Models\Server\Inbox>
     */
    protected $model = Inbox::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'user_id' => User::factory(),
            'thread_id' => null,
            'inboxable_type' => Thread::class,
            'inboxable_id' => Thread::factory(),
            'kind' => Inbox::KindThread,
            'status' => Inbox::StatusUnread,
            'title' => fake()->sentence(4),
            'summary' => fake()->sentence(),
            'source' => 'thread_summary',
            'payload' => [
                'preview' => fake()->sentence(),
                'context_state' => 'summary',
            ],
            'read_at' => null,
            'archived_at' => null,
        ];
    }
}
