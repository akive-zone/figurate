<?php

namespace Tests\Feature;

use App\Features\Actions\Conversation\EnqueueThreadMessageOutbox;
use App\Features\Actions\Conversation\Protocols\ChannelProtocol;
use App\Jobs\DeliverOutboxMessage;
use App\Models\Server\Channel;
use App\Models\Server\Outbox;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnqueueThreadMessageOutboxChannelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_enqueues_channel_outbox_records_for_active_thread_channel_addresses(): void
    {
        Queue::fake();
        Storage::fake('public');

        $sender = User::factory()->create();
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
            'kind' => 'link',
            'status' => 'active',
            'direction' => Channel::DirectionOutbound,
            'data' => [],
        ]);
        $route = $channel->routes()->create([
            'name' => 'primary',
            'status' => Channel::StatusActive,
            'direction' => Channel::DirectionOutbound,
            'config' => [
                'outbound' => [
                    'transport' => Channel::TransportHttp,
                    'endpoint_url' => 'https://channels.example/send',
                ],
            ],
            'data' => [
                'session' => 'default',
            ],
            'meta' => [],
        ]);
        $route->addMediaFromString(<<<'MARKDOWN'
---
name: waha-http-send
description: Shape outbound WAHA HTTP payloads.
---

# WAHA HTTP Send

Use chatId and text for outbound delivery.
MARKDOWN)
            ->usingName('waha-http-send')
            ->usingFileName('waha-http-send.md')
            ->withCustomProperties([
                'skill_slug' => 'waha-http-send',
                'description' => 'Shape outbound WAHA HTTP payloads.',
            ])
            ->toMediaCollection(Channel::SkillCollection, 'public');
        $address = $route->addresses()->create([
            'addressable_type' => $thread->getMorphClass(),
            'addressable_id' => $thread->getKey(),
            'provider' => 'waha',
            'target' => '2348012345678@c.us',
            'target_type' => 'whatsapp_chat',
            'status' => Channel::StatusActive,
            'direction' => Channel::DirectionOutbound,
            'data' => [
                'phone' => '+2348012345678',
            ],
            'meta' => [],
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
        $this->assertSame('waha', $outbox->provider);
        $this->assertSame('2348012345678@c.us', $outbox->target);
        $this->assertSame('thread.post.created', data_get($outbox->payload, 'event'));
        $this->assertSame($channel->uuid, data_get($outbox->payload, 'channel.uuid'));
        $this->assertSame($route->id, data_get($outbox->payload, 'route.id'));
        $this->assertSame($address->id, data_get($outbox->payload, 'address.id'));
        $this->assertSame('2348012345678@c.us', data_get($outbox->payload, 'address.target'));
        $this->assertSame($sender->id, data_get($outbox->payload, 'sender.id'));
        $this->assertSame('2348012345678@c.us', data_get($outbox->payload, 'recipients.0.target'));
        $this->assertSame('https://channels.example/send', data_get($outbox->payload, 'delivery.route.config.outbound.endpoint_url'));
        $this->assertSame('waha-http-send', data_get($outbox->payload, 'delivery.skill_context.entries.0.slug'));

        Queue::assertPushed(DeliverOutboxMessage::class, function (DeliverOutboxMessage $job) use ($outbox): bool {
            return $job->outboxId === $outbox->id;
        });
    }
}
