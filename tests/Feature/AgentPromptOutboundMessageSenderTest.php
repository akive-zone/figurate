<?php

namespace Tests\Feature;

use App\Ai\Storage\ConversationPersistenceResolver;
use App\Ai\Support\AgentExecutor;
use App\Features\Actions\Conversation\AgentPromptOutboundMessageSender;
use App\Features\Actions\Conversation\Protocols\AgentPromptProtocol;
use App\Models\Server\Outbox;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AgentPromptOutboundMessageSenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_invokes_the_chat_agent_executor_from_an_agent_prompt_outbox_record(): void
    {
        $recipient = User::factory()->create([
            'type' => User::TypeRobot,
        ]);
        $sender = User::factory()->create();
        $space = Space::factory()->create();
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Prompt Sender Thread',
            'phase' => 'execution',
            'status' => 'open',
        ]);
        $post = $thread->posts()->create([
            'type' => Post::TypeMessage,
            'status' => Post::StatusActive,
            'data' => [
                'text' => 'Prepare a concise answer.',
                'message_type' => 'text',
            ],
            'senderable_type' => $sender->getMorphClass(),
            'senderable_id' => $sender->id,
            'meta' => [
                'source' => 'peer_message',
            ],
        ]);
        $presenter = $thread->actors()->create([
            'actorable_type' => ThreadActor::ActorCoordinator,
            'actorable_id' => null,
            'role' => ThreadActor::RolePresenter,
            'status' => ThreadActor::StatusActive,
            'priority' => 1,
            'config' => null,
        ]);
        $outbox = Outbox::query()->create([
            'thread_id' => $thread->id,
            'post_id' => $post->id,
            'direction' => Outbox::DirectionOutbound,
            'protocol' => AgentPromptProtocol::Key,
            'provider' => 'laravel-ai',
            'target' => ThreadActor::ActorCoordinator,
            'status' => Outbox::StatusPending,
            'attempts' => 0,
            'available_at' => now(),
            'idempotency_key' => 'prompt:test',
            'payload' => [
                'dispatch' => [
                    'recipient_user_id' => $recipient->id,
                    'thread_actor_id' => $presenter->id,
                    'broadcast_space_id' => "threads.{$thread->uuid}",
                    'conversation_persistence' => ConversationPersistenceResolver::ThreadCompletion,
                ],
            ],
        ]);

        $executor = Mockery::mock(AgentExecutor::class);
        $executor->shouldReceive('queue')
            ->once()
            ->withArgs(function (
                Thread $queuedThread,
                Post $queuedMessage,
                User $queuedUser,
                ThreadActor $queuedPresenter,
                string $broadcastSpaceId,
                ?string $conversationPersistenceMode,
            ) use ($thread, $post, $recipient, $presenter): bool {
                return $queuedThread->is($thread)
                    && $queuedMessage->is($post)
                    && $queuedUser->is($recipient)
                    && $queuedPresenter->is($presenter)
                    && $broadcastSpaceId === "threads.{$thread->uuid}"
                    && $conversationPersistenceMode === ConversationPersistenceResolver::ThreadCompletion;
            });

        $result = (new AgentPromptOutboundMessageSender($executor))->send($outbox);

        $this->assertTrue((bool) data_get($result, 'ok'));
        $this->assertSame(AgentPromptProtocol::Key, data_get($result, 'protocol'));
        $this->assertSame('queued_for_agent', data_get($result, 'delivery'));
        $this->assertSame($presenter->id, data_get($result, 'thread_actor_id'));
        $this->assertSame(
            ConversationPersistenceResolver::ThreadCompletion,
            data_get($result, 'conversation_persistence'),
        );
    }
}
