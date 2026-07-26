<?php

namespace Tests\Feature;

use App\Features\Actions\Chat\ChannelOutboundMessageSender;
use App\Features\Actions\Chat\Protocols\ChannelProtocol;
use App\Models\Server\Channel;
use App\Models\Server\Outbox;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelOutboundMessageSenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_through_the_configured_channel_driver(): void
    {
        $sender = User::factory()->create();
        $space = Space::factory()->create();
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Channel Sender Thread',
            'phase' => 'execution',
            'status' => 'open',
        ]);
        $post = $thread->posts()->create([
            'type' => Post::TypeMessage,
            'status' => Post::StatusActive,
            'data' => [
                'text' => 'Send this update externally.',
                'message_type' => 'text',
            ],
            'meta' => [
                'source' => 'peer_message',
            ],
        ]);
        $post->attachRelation($sender, Post::RelationRoleSender);
        $channel = Channel::factory()->create([
            'driver' => Channel::ProtocolGeneric,
        ]);
        $outbox = Outbox::query()->create([
            'thread_id' => $thread->id,
            'post_id' => $post->id,
            'direction' => Outbox::DirectionOutbound,
            'protocol' => ChannelProtocol::Key,
            'provider' => Channel::ProtocolGeneric,
            'target' => 'provider-thread-123',
            'status' => Outbox::StatusPending,
            'attempts' => 0,
            'available_at' => now(),
            'idempotency_key' => 'channel:test',
            'payload' => [
                'event' => 'thread.post.created',
                'channel' => [
                    'uuid' => $channel->uuid,
                ],
                'route' => [
                    'id' => 21,
                    'name' => 'primary',
                    'config' => [
                        'outbound' => [
                            'transport' => Channel::TransportHttp,
                            'endpoint_url' => 'https://channels.example/send',
                        ],
                    ],
                ],
                'address' => [
                    'id' => 34,
                    'provider' => Channel::ProtocolGeneric,
                    'target' => 'provider-thread-123',
                ],
                'thread' => [
                    'uuid' => $thread->uuid,
                ],
                'post' => [
                    'ulid' => $post->ulid,
                    'text' => 'Send this update externally.',
                ],
                'sender' => [
                    'id' => $sender->id,
                    'type' => $sender->getMorphClass(),
                ],
                'recipients' => [[
                    'target' => 'provider-thread-123',
                ]],
                'delivery' => [
                    'provider' => Channel::ProtocolGeneric,
                    'target' => 'provider-thread-123',
                    'channel' => [
                        'uuid' => $channel->uuid,
                    ],
                    'route' => [
                        'id' => 21,
                        'config' => [
                            'outbound' => [
                                'transport' => Channel::TransportHttp,
                                'endpoint_url' => 'https://channels.example/send',
                            ],
                        ],
                    ],
                    'address' => [
                        'id' => 34,
                        'target' => 'provider-thread-123',
                    ],
                ],
            ],
        ]);

        $result = app(ChannelOutboundMessageSender::class)->send($outbox);

        $this->assertTrue((bool) data_get($result, 'ok'));
        $this->assertSame(ChannelProtocol::Key, data_get($result, 'protocol'));
        $this->assertSame(Channel::ProtocolGeneric, data_get($result, 'provider'));
        $this->assertSame('dispatched', data_get($result, 'delivery'));
        $this->assertSame($channel->uuid, data_get($result, 'channel.uuid'));
        $this->assertSame('provider-thread-123', data_get($result, 'channel_result.provider_identifier'));
        $this->assertSame('thread.post.created', data_get($result, 'channel_result.payload.event'));
        $this->assertSame($sender->id, data_get($result, 'channel_result.payload.sender.id'));
        $this->assertSame('provider-thread-123', data_get($result, 'channel_result.payload.recipients.0.target'));
    }
}
