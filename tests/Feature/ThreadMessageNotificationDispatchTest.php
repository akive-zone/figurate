<?php

namespace Tests\Feature;

use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
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

    public function test_it_sends_a_thread_message_notification_to_other_active_human_participants(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $channel = Channel::query()->create([
            'driver' => Channel::DriverGeneric,
            'status' => 'open',
        ]);
        $thread = $channel->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Coordination Thread',
            'phase' => 'coordination',
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
            'actorable_type' => $recipient->getMorphClass(),
            'actorable_id' => $recipient->id,
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
            'actorable_type' => $recipient->getMorphClass(),
            'actorable_id' => $recipient->id,
            'role' => ThreadActor::RoleMember,
            'status' => ThreadActor::StatusActive,
            'priority' => 2,
            'config' => null,
        ]);

        Sanctum::actingAs($sender, [TokenAbility::Compose->value]);

        $this->postJson('/api/form', [
            'channel' => $channel->uuid,
            'thread' => $thread->uuid,
            'content' => [
                'text' => 'Please confirm the arrival time.',
            ],
        ])->assertOk();

        $notification = DB::table('notifications')
            ->where('notifiable_type', $recipient->getMorphClass())
            ->where('notifiable_id', $recipient->id)
            ->where('type', ThreadMessageNotification::class)
            ->first();

        $this->assertNotNull($notification);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => $sender->getMorphClass(),
            'notifiable_id' => $sender->id,
            'type' => ThreadMessageNotification::class,
        ]);

        $payload = json_decode((string) $notification->data, true);

        $this->assertSame($channel->uuid, data_get($payload, 'channel.id'));
        $this->assertSame($thread->uuid, data_get($payload, 'thread.id'));
        $this->assertSame('peer_message', data_get($payload, 'message.source'));
        $this->assertSame('New message', data_get($payload, 'inbox.title'));
        $this->assertSame('Please confirm the arrival time.', data_get($payload, 'inbox.summary'));
    }
}
