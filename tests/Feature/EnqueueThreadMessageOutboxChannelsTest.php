<?php

namespace Tests\Feature;

use App\Features\Actions\Conversation\EnqueueThreadMessageOutbox;
use App\Features\Actions\Conversation\Protocols\ChannelProtocol;
use App\Jobs\DeliverOutboxMessage;
use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use App\Models\Server\Outbox;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EnqueueThreadMessageOutboxChannelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_enqueues_channel_outbox_records_for_active_thread_channel_bindings(): void
    {
        Queue::fake();

        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $space = Space::factory()->create();
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Channel Outbox Thread',
            'phase' => 'execution',
            'status' => 'open',
        ]);
        $channel = Channel::factory()->create([
            'driver' => Channel::ProtocolGeneric,
        ]);

        $thread->channelRelations()->create([
            'channel_id' => $channel->id,
            'kind' => ChannelRelation::KindBind,
            'status' => 'active',
            'direction' => Channel::DirectionOutbound,
            'data' => [
                'actor_id' => $sender->id,
                'actor_type' => $sender->getMorphClass(),
                'provider_identifier' => 'internal:user:'.$sender->id,
                'provider_kind' => 'user',
                'delivery_scope' => 'in_app',
                'delivery_mode' => 'realtime',
                'address' => ['user_id' => $sender->id],
                'config' => ['route' => 'self'],
            ],
        ]);
        $recipientBinding = $thread->channelRelations()->create([
            'channel_id' => $channel->id,
            'kind' => ChannelRelation::KindBind,
            'status' => 'active',
            'direction' => Channel::DirectionOutbound,
            'data' => [
                'actor_id' => $recipient->id,
                'actor_type' => $recipient->getMorphClass(),
                'provider_identifier' => 'internal:user:'.$recipient->id,
                'provider_kind' => 'user',
                'delivery_scope' => 'in_app',
                'delivery_mode' => 'realtime',
                'address' => ['user_id' => $recipient->id],
                'config' => ['route' => 'primary'],
            ],
        ]);

        $post = $thread->posts()->create([
            'type' => Post::TypeMessage,
            'status' => Post::StatusActive,
            'data' => [
                'text' => 'External delivery payload',
                'message_type' => 'text',
            ],
            'meta' => [
                'source' => 'peer_message',
            ],
        ]);
        $post->attachRelation($sender, Post::RelationRoleSender);

        $created = app(EnqueueThreadMessageOutbox::class)->execute($post);

        $this->assertCount(1, $created);
        $outbox = $created->first();
        $this->assertInstanceOf(Outbox::class, $outbox);
        $this->assertSame(ChannelProtocol::Key, $outbox->protocol);
        $this->assertSame(Channel::ProtocolGeneric, $outbox->provider);
        $this->assertSame('internal:user:'.$recipient->id, $outbox->target);
        $this->assertSame('thread.post.created', data_get($outbox->payload, 'event'));
        $this->assertSame($channel->uuid, data_get($outbox->payload, 'channel.uuid'));
        $this->assertSame($recipientBinding->id, data_get($outbox->payload, 'binding.id'));
        $this->assertSame('internal:user:'.$recipient->id, data_get($outbox->payload, 'binding.provider_identifier'));
        $this->assertSame($sender->id, data_get($outbox->payload, 'sender.id'));
        $this->assertSame($recipient->id, data_get($outbox->payload, 'recipients.0.actor_id'));
        $this->assertSame('primary', data_get($outbox->payload, 'delivery.binding.config.route'));

        Queue::assertPushed(DeliverOutboxMessage::class, function (DeliverOutboxMessage $job) use ($outbox): bool {
            return $job->outboxId === $outbox->id;
        });
    }
}
