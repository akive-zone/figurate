<?php

namespace Tests\Feature;

use App\Ai\Storage\ConversationPersistenceResolver;
use App\Models\Server\Inbox;
use App\Models\Server\Message;
use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\ThreadEvent;
use App\Models\Server\User;
use App\Notifications\Server\Chat\ThreadMessageNotification;
use App\TokenAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ThreadMessageNotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_routes_thread_message_notifications_through_inbox_and_coordination_channels(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $robot = User::factory()->create([
            'type' => User::TypeRobot,
        ]);
        $space = Space::query()->create([
            'status' => 'open',
        ]);
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Coordination Thread',
            'phase' => 'coordination',
            'status' => 'open',
        ]);

        SpaceActorState::query()->create([
            'space_id' => $space->id,
            'thread_id' => null,
            'actorable_type' => $sender->getMorphClass(),
            'actorable_id' => $sender->id,
            'status' => SpaceActorState::StatusActive,
        ]);
        SpaceActorState::query()->create([
            'space_id' => $space->id,
            'thread_id' => null,
            'actorable_type' => $recipient->getMorphClass(),
            'actorable_id' => $recipient->id,
            'status' => SpaceActorState::StatusActive,
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
            'actorable_type' => $recipient->getMorphClass(),
            'actorable_id' => $recipient->id,
            'role' => ThreadActor::RoleMember,
            'status' => ThreadActor::StatusActive,
            'priority' => 2,
            'config' => null,
        ]);
        $robotActor = $thread->actors()->create([
            'actorable_type' => $robot->getMorphClass(),
            'actorable_id' => $robot->id,
            'role' => ThreadActor::RoleMember,
            'status' => ThreadActor::StatusActive,
            'priority' => 3,
            'config' => null,
        ]);

        Sanctum::actingAs($sender, [TokenAbility::Compose->value]);

        $response = $this->postJson('/api/conversations', [
            'space' => $space->uuid,
            'thread' => $thread->uuid,
            'content' => [
                'text' => 'Please confirm the arrival time.',
            ],
        ])->assertOk();

        $messageId = (int) $response->json('message_id');

        $this->assertDatabaseHas('inboxes', [
            'user_id' => $recipient->id,
            'thread_id' => $thread->id,
            'inboxable_type' => (new Message)->getMorphClass(),
            'inboxable_id' => $messageId,
            'kind' => Inbox::KindThreadMessage,
            'title' => 'New message',
            'summary' => 'Please confirm the arrival time.',
        ]);
        $this->assertDatabaseMissing('inboxes', [
            'user_id' => $sender->id,
            'inboxable_id' => $messageId,
            'kind' => Inbox::KindThreadMessage,
        ]);
        $this->assertDatabaseMissing('inboxes', [
            'user_id' => $robot->id,
            'inboxable_id' => $messageId,
            'kind' => Inbox::KindThreadMessage,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => $recipient->getMorphClass(),
            'notifiable_id' => $recipient->id,
            'type' => ThreadMessageNotification::class,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => $robot->getMorphClass(),
            'notifiable_id' => $robot->id,
            'type' => ThreadMessageNotification::class,
        ]);

        $event = ThreadEvent::query()
            ->where('thread_id', $thread->id)
            ->where('message_id', $messageId)
            ->where('event_type', 'orchestration.notification.coordination_requested')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame(ThreadEvent::KindOrchestration, $event->kind);
        $this->assertSame('notification.space.coordination', $event->operation);
        $this->assertSame($robotActor->id, $event->thread_actor_id);
        $this->assertSame($robot->id, data_get($event->payload, 'recipient_user_id'));
        $this->assertSame($robot->uuid, data_get($event->payload, 'recipient_user_uuid'));
        $this->assertSame($space->uuid, data_get($event->payload, 'space_uuid'));
        $this->assertSame('peer_message', data_get($event->payload, 'source'));
    }

    public function test_it_routes_robot_notifications_to_the_completion_transport_when_requested(): void
    {
        $sender = User::factory()->create();
        $robot = User::factory()->create([
            'type' => User::TypeRobot,
        ]);
        $space = Space::query()->create([
            'status' => 'open',
        ]);
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Completion Thread',
            'phase' => 'execution',
            'status' => 'open',
        ]);

        SpaceActorState::query()->create([
            'space_id' => $space->id,
            'thread_id' => null,
            'actorable_type' => $sender->getMorphClass(),
            'actorable_id' => $sender->id,
            'status' => SpaceActorState::StatusActive,
        ]);
        SpaceActorState::query()->create([
            'space_id' => $space->id,
            'thread_id' => null,
            'actorable_type' => $robot->getMorphClass(),
            'actorable_id' => $robot->id,
            'status' => SpaceActorState::StatusActive,
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

        Sanctum::actingAs($sender, [TokenAbility::Compose->value]);

        $response = $this->postJson('/api/conversations', [
            'space' => $space->uuid,
            'thread' => $thread->uuid,
            'conversation_persistence' => ConversationPersistenceResolver::ThreadCompletion,
            'content' => [
                'text' => 'Compile the latest update.',
            ],
        ])->assertOk();

        $messageId = (int) $response->json('message_id');

        $notification = DB::table('notifications')
            ->where('notifiable_type', $robot->getMorphClass())
            ->where('notifiable_id', $robot->id)
            ->where('type', ThreadMessageNotification::class)
            ->first();

        $this->assertNotNull($notification);

        $payload = json_decode((string) $notification->data, true);

        $this->assertSame(
            ConversationPersistenceResolver::ThreadCompletion,
            data_get($payload, 'message.conversation_persistence')
        );

        $event = ThreadEvent::query()
            ->where('thread_id', $thread->id)
            ->where('message_id', $messageId)
            ->where('event_type', 'orchestration.notification.completion_missing_presenter')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('notification.space.completion', $event->operation);
        $this->assertSame('missing_presenter', data_get($event->payload, 'reason'));
        $this->assertSame(
            ConversationPersistenceResolver::ThreadCompletion,
            data_get($event->payload, 'conversation_persistence')
        );
        $this->assertDatabaseMissing('inboxes', [
            'user_id' => $robot->id,
            'inboxable_id' => $messageId,
            'kind' => Inbox::KindThreadMessage,
        ]);
    }
}
