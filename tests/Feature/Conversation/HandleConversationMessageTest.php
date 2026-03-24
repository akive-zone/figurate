<?php

namespace Tests\Feature\Chat;

use App\Ai\Support\ChatAgentExecutor;
use App\Jobs\ProcessThreadObservers;
use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use App\TokenAbility;
use Database\Factories\ChannelFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class HandleConversationMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_presenters_for_presenter_threads_without_observer_dispatch(): void
    {
        Queue::fake();

        $this->mock(ChatAgentExecutor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('queue')->once();
        });

        $user = $this->makeUser();
        $channel = $this->accessibleChannel($user);
        $thread = $this->makeThread($channel);
        $this->addThreadActor($thread, ThreadActor::RolePresenter, ThreadActor::ActorRequestAgent);

        Sanctum::actingAs($user, [TokenAbility::Chat->value]);

        $this->postJson('/api/conversations', [
            'channel' => $channel->uuid,
            'thread' => $thread->uuid,
            'content' => [
                'text' => 'Please help me scope the repair.',
            ],
        ])
            ->assertAccepted()
            ->assertJsonPath('interaction_mode', 'presenter')
            ->assertJsonPath('observer_status', 'none')
            ->assertJsonPath('pending', true)
            ->assertJsonPath('pending_presenters', 1);

        $message = Message::query()->latest('id')->firstOrFail();

        $this->assertSame('agent_prompt', data_get($message->meta, 'source'));
        $this->assertFalse((bool) data_get($message->meta, 'observer_dispatch'));
        Queue::assertNothingPushed();
    }

    public function test_it_queues_observers_for_peer_threads(): void
    {
        Queue::fake();

        $this->mock(ChatAgentExecutor::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('queue');
        });

        $user = $this->makeUser();
        $channel = $this->accessibleChannel($user);
        $thread = $this->makeThread($channel);
        $this->addThreadActor($thread, ThreadActor::RoleObserver, ThreadActor::ActorSafetyGuard);

        Sanctum::actingAs($user, [TokenAbility::Chat->value]);

        $this->postJson('/api/conversations', [
            'channel' => $channel->uuid,
            'thread' => $thread->uuid,
            'content' => [
                'text' => 'I have shared the details with the artisan.',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('interaction_mode', 'peer')
            ->assertJsonPath('observer_status', 'queued')
            ->assertJsonPath('pending', false)
            ->assertJsonPath('pending_presenters', 0);

        $message = Message::query()->latest('id')->firstOrFail();

        $this->assertSame('peer_message', data_get($message->meta, 'source'));
        $this->assertTrue((bool) data_get($message->meta, 'observer_dispatch'));
        Queue::assertPushed(ProcessThreadObservers::class, 1);
    }

    public function test_it_supports_hybrid_threads_by_queueing_both_presenters_and_observers(): void
    {
        Queue::fake();

        $this->mock(ChatAgentExecutor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('queue')->once();
        });

        $user = $this->makeUser();
        $channel = $this->accessibleChannel($user);
        $thread = $this->makeThread($channel);
        $this->addThreadActor($thread, ThreadActor::RolePresenter, ThreadActor::ActorRequestAgent);
        $this->addThreadActor($thread, ThreadActor::RoleObserver, ThreadActor::ActorSafetyGuard, 2);

        Sanctum::actingAs($user, [TokenAbility::Chat->value]);

        $this->postJson('/api/conversations', [
            'channel' => $channel->uuid,
            'thread' => $thread->uuid,
            'content' => [
                'text' => 'We are confirming the visit window now.',
            ],
        ])
            ->assertAccepted()
            ->assertJsonPath('interaction_mode', 'hybrid')
            ->assertJsonPath('observer_status', 'queued')
            ->assertJsonPath('pending', true)
            ->assertJsonPath('pending_presenters', 1);

        $message = Message::query()->latest('id')->firstOrFail();

        $this->assertSame('agent_prompt', data_get($message->meta, 'source'));
        $this->assertTrue((bool) data_get($message->meta, 'observer_dispatch'));
        Queue::assertPushed(ProcessThreadObservers::class, 1);
    }

    public function test_it_returns_turns_for_a_conversation_message_from_the_dedicated_endpoint(): void
    {
        $user = $this->makeUser();
        $channel = $this->accessibleChannel($user);
        $thread = $this->makeThread($channel);

        $promptMessage = $thread->messages()->create([
            'type' => 'text',
            'text' => 'Please help me scope the repair.',
            'senderable_type' => $user->getMorphClass(),
            'senderable_id' => $user->id,
            'meta' => [
                'source' => 'agent_prompt',
            ],
        ]);

        $assistantMessage = $thread->messages()->create([
            'type' => 'text',
            'text' => 'Start by listing the visible damage and confirming access.',
            'meta' => [
                'source' => 'agent_response',
                'in_reply_to_message_id' => $promptMessage->id,
                'actor_key' => 'request_agent',
            ],
        ]);

        Sanctum::actingAs($user, [TokenAbility::Chat->value]);

        $this->getJson(sprintf('/api/conversations/%s/messages/%d/turns', $channel->uuid, $promptMessage->id))
            ->assertOk()
            ->assertJsonPath('thread', $thread->uuid)
            ->assertJsonPath('message_id', $promptMessage->id)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.prompt_message_id', $promptMessage->id)
            ->assertJsonPath('data.0.assistant_message_id', $assistantMessage->id)
            ->assertJsonPath('data.0.status', 'completed');
    }

    protected function accessibleChannel(User $user): Channel
    {
        $channel = ChannelFactory::new()->create();

        ChannelActorState::query()->create([
            'channel_id' => $channel->id,
            'thread_id' => null,
            'actorable_type' => $user->getMorphClass(),
            'actorable_id' => $user->id,
            'status' => ChannelActorState::StatusActive,
        ]);

        return $channel;
    }

    protected function makeThread(Channel $channel): Thread
    {
        return $channel->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Main Request Thread',
            'phase' => 'request_intake',
            'status' => 'open',
        ]);
    }

    protected function addThreadActor(Thread $thread, string $role, string $actorableType, int $priority = 1): void
    {
        $thread->actors()->create([
            'actorable_type' => $actorableType,
            'actorable_id' => null,
            'role' => $role,
            'status' => ThreadActor::StatusActive,
            'priority' => $priority,
            'config' => null,
        ]);
    }

    protected function makeUser(): User
    {
        return User::query()->create([
            'name' => 'Chat Tester',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'type' => User::TypeSubject,
            'status' => 'active',
        ]);
    }
}
