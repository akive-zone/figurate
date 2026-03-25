<?php

namespace Tests\Feature;

use App\Ai\Storage\ConversationPersistenceResolver;
use App\Features\Actions\Conversation\EnqueueThreadPromptOutbox;
use App\Features\Actions\Conversation\Protocols\AgentPromptProtocol;
use App\Jobs\DeliverOutboxMessage;
use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Inbox;
use App\Models\Server\Outbox;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\ThreadEvent;
use App\Models\Server\User;
use App\TokenAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentPromptOutboxDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_enqueues_an_agent_prompt_outbox_dispatch_from_completion_notifications(): void
    {
        Queue::fake();

        $sender = User::factory()->create();
        $robot = User::factory()->create([
            'type' => User::TypeRobot,
        ]);
        $channel = Channel::factory()->create();
        $thread = $channel->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Prompt Dispatch Thread',
            'phase' => 'execution',
            'status' => 'open',
        ]);

        ChannelActorState::query()->create([
            'channel_id' => $channel->id,
            'thread_id' => null,
            'actorable_type' => $sender->getMorphClass(),
            'actorable_id' => $sender->id,
            'status' => ChannelActorState::StatusActive,
        ]);
        ChannelActorState::query()->create([
            'channel_id' => $channel->id,
            'thread_id' => null,
            'actorable_type' => $robot->getMorphClass(),
            'actorable_id' => $robot->id,
            'status' => ChannelActorState::StatusActive,
        ]);

        $thread->actors()->create([
            'actorable_type' => $sender->getMorphClass(),
            'actorable_id' => $sender->id,
            'role' => ThreadActor::RoleMember,
            'status' => ThreadActor::StatusActive,
            'priority' => 1,
            'config' => null,
        ]);
        $thread->actors()->create([
            'actorable_type' => $robot->getMorphClass(),
            'actorable_id' => $robot->id,
            'role' => ThreadActor::RoleMember,
            'status' => ThreadActor::StatusActive,
            'priority' => 2,
            'config' => null,
        ]);
        $presenter = $thread->actors()->create([
            'actorable_type' => ThreadActor::ActorRequestAgent,
            'actorable_id' => null,
            'role' => ThreadActor::RolePresenter,
            'status' => ThreadActor::StatusActive,
            'priority' => 3,
            'config' => null,
        ]);

        Sanctum::actingAs($sender, [TokenAbility::Compose->value]);

        $response = $this->postJson('/api/conversations', [
            'channel' => $channel->uuid,
            'thread' => $thread->uuid,
            'conversation_persistence' => ConversationPersistenceResolver::ThreadCompletion,
            'content' => [
                'text' => 'Compile the latest update.',
            ],
        ])->assertAccepted();

        $messageId = (int) $response->json('message_id');

        $outbox = Outbox::query()
            ->where('thread_id', $thread->id)
            ->where('message_id', $messageId)
            ->where('protocol', AgentPromptProtocol::Key)
            ->first();

        $this->assertNotNull($outbox);
        $this->assertSame(Outbox::DirectionOutbound, $outbox->direction);
        $this->assertSame('laravel-ai', $outbox->provider);
        $this->assertSame(ThreadActor::ActorRequestAgent, $outbox->target);
        $this->assertSame(
            ConversationPersistenceResolver::ThreadCompletion,
            data_get($outbox->payload, 'dispatch.conversation_persistence'),
        );
        $this->assertSame($robot->id, data_get($outbox->payload, 'dispatch.recipient_user_id'));
        $this->assertSame($presenter->id, data_get($outbox->payload, 'dispatch.thread_actor_id'));

        Queue::assertPushed(DeliverOutboxMessage::class, function (DeliverOutboxMessage $job) use ($outbox): bool {
            return $job->outboxId === $outbox->id;
        });

        $event = ThreadEvent::query()
            ->where('thread_id', $thread->id)
            ->where('message_id', $messageId)
            ->where('event_type', 'orchestration.notification.completion_requested')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('notification.channel.completion', $event->operation);
        $this->assertSame('outbox_enqueued', data_get($event->payload, 'reason'));
        $this->assertSame($outbox->id, data_get($event->payload, 'outbox_id'));
        $this->assertSame(AgentPromptProtocol::Key, data_get($event->payload, 'outbox_protocol'));

        $this->assertDatabaseMissing('inboxes', [
            'user_id' => $robot->id,
            'inboxable_id' => $messageId,
            'kind' => Inbox::KindThreadMessage,
        ]);
    }

    public function test_it_is_idempotent_when_enqueuing_the_same_prompt_dispatch_twice(): void
    {
        Queue::fake();

        $recipient = User::factory()->create([
            'type' => User::TypeRobot,
        ]);
        $sender = User::factory()->create();
        $channel = Channel::factory()->create();
        $thread = $channel->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Prompt Idempotency Thread',
            'phase' => 'execution',
            'status' => 'open',
        ]);
        $message = $thread->messages()->create([
            'type' => 'text',
            'text' => 'Generate a summary.',
            'senderable_type' => $sender->getMorphClass(),
            'senderable_id' => $sender->id,
            'meta' => [
                'source' => 'peer_message',
            ],
        ]);
        $presenter = $thread->actors()->create([
            'actorable_type' => ThreadActor::ActorRequestAgent,
            'actorable_id' => null,
            'role' => ThreadActor::RolePresenter,
            'status' => ThreadActor::StatusActive,
            'priority' => 1,
            'config' => null,
        ]);

        $action = app(EnqueueThreadPromptOutbox::class);

        $first = $action->execute(
            thread: $thread,
            message: $message,
            recipient: $recipient,
            threadActor: $presenter,
            conversationPersistenceMode: ConversationPersistenceResolver::ThreadContinuation,
        );
        $second = $action->execute(
            thread: $thread,
            message: $message,
            recipient: $recipient,
            threadActor: $presenter,
            conversationPersistenceMode: ConversationPersistenceResolver::ThreadContinuation,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('outboxes', 1);

        Queue::assertPushed(DeliverOutboxMessage::class, 1);
    }
}
