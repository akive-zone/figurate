<?php

namespace Tests\Feature\Channels;

use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use App\Support\Channels\Transports\WebSocketServerTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class WebSocketServerTransportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_broadcasts_messages_via_websocket_server(): void
    {
        Broadcast::shouldReceive('channel')
            ->once()
            ->with('thread.test-thread-uuid')
            ->andReturnSelf();

        Broadcast::shouldReceive('send')
            ->once()
            ->with($this->callback(function ($payload) {
                return is_array($payload)
                    && $payload['event'] === 'thread.post.created';
            }));

        $sender = User::factory()->create();
        $space = Space::factory()->create();
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'WebSocket Server Test',
            'phase' => 'execution',
            'status' => 'open',
            'uuid' => 'test-thread-uuid',
        ]);
        $post = $thread->posts()->create([
            'type' => Post::TypeMessage,
            'status' => Post::StatusActive,
            'data' => [
                'text' => 'Test WebSocket server broadcast',
                'message_type' => 'text',
            ],
        ]);
        $post->attachRelation($sender, Post::RelationRoleSender);

        $channel = Channel::factory()->create([
            'driver' => 'websocket',
            'config' => [
                'mode' => 'server',
            ],
        ]);

        $bindingConfig = [
            'provider_identifier' => 'test-ws-server-123',
            'outbound_payload' => [
                'event' => 'thread.post.created',
                'thread' => [
                    'uuid' => $thread->uuid,
                ],
                'post' => [
                    'ulid' => $post->ulid,
                    'text' => 'Test WebSocket server broadcast',
                ],
            ],
        ];

        $transport = app(WebSocketServerTransport::class);
        $result = $transport->deliver($channel, $thread, $post, $bindingConfig);

        $this->assertSame('broadcast', $result['status']);
        $this->assertSame('websocket', $result['provider']);
        $this->assertSame('thread.test-thread-uuid', $result['channel_name']);
        $this->assertSame('websocket-server', $result['transport']);
        $this->assertSame('server', $result['mode']);
        $this->assertSame('test-ws-server-123', $result['provider_identifier']);
    }

    public function test_it_uses_custom_channel_name_from_config(): void
    {
        Broadcast::shouldReceive('channel')
            ->once()
            ->with('custom.channel.name')
            ->andReturnSelf();

        Broadcast::shouldReceive('send')->once();

        $sender = User::factory()->create();
        $space = Space::factory()->create();
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'phase' => 'execution',
            'status' => 'open',
        ]);
        $post = $thread->posts()->create([
            'type' => Post::TypeMessage,
            'status' => Post::StatusActive,
            'data' => ['text' => 'Test'],
        ]);
        $post->attachRelation($sender, Post::RelationRoleSender);

        $channel = Channel::factory()->create([
            'driver' => 'websocket',
            'handler' => 'custom.channel.name',
        ]);

        $bindingConfig = [
            'outbound_payload' => [
                'event' => 'test',
            ],
        ];

        $transport = app(WebSocketServerTransport::class);
        $result = $transport->deliver($channel, $thread, $post, $bindingConfig);

        $this->assertSame('custom.channel.name', $result['channel_name']);
    }
}
