<?php

namespace Tests\Feature\Channels;

use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use App\Support\Channels\Transports\WebSocketClientTransport;
use App\Support\Channels\WebSocket\WebSocketConnection;
use App\Support\Channels\WebSocket\WebSocketConnectionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebSocketClientTransportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_delivers_messages_via_websocket_client(): void
    {
        $sender = User::factory()->create();
        $space = Space::factory()->create();
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'WebSocket Client Test',
            'phase' => 'execution',
            'status' => 'open',
        ]);
        $post = $thread->posts()->create([
            'type' => Post::TypeMessage,
            'status' => Post::StatusActive,
            'data' => [
                'text' => 'Test WebSocket client delivery',
                'message_type' => 'text',
            ],
        ]);
        $post->attachRelation($sender, Post::RelationRoleSender);

        $channel = Channel::factory()->create([
            'driver' => 'websocket',
            'endpoint_url' => 'wss://echo.websocket.org/',
            'config' => [
                'mode' => 'client',
            ],
        ]);

        $bindingConfig = [
            'provider_identifier' => 'test-ws-client-123',
            'outbound_payload' => [
                'event' => 'thread.post.created',
                'thread' => [
                    'uuid' => $thread->uuid,
                ],
                'post' => [
                    'ulid' => $post->ulid,
                    'text' => 'Test WebSocket client delivery',
                ],
            ],
        ];

        // Mock the connection manager to avoid actual WebSocket connection in tests
        $mockConnection = $this->createMock(WebSocketConnection::class);
        $mockConnection->expects($this->once())
            ->method('send')
            ->with($this->isType('string'));

        $mockManager = $this->createMock(WebSocketConnectionManager::class);
        $mockManager->expects($this->once())
            ->method('getOrCreateConnection')
            ->willReturn($mockConnection);

        $transport = new WebSocketClientTransport($mockManager);
        $result = $transport->deliver($channel, $thread, $post, $bindingConfig);

        $this->assertSame('sent', $result['status']);
        $this->assertSame('websocket', $result['provider']);
        $this->assertSame('wss://echo.websocket.org/', $result['endpoint_url']);
        $this->assertSame('websocket-client', $result['transport']);
        $this->assertSame('client', $result['mode']);
        $this->assertSame('test-ws-client-123', $result['provider_identifier']);
    }

    public function test_it_throws_exception_when_websocket_endpoint_is_missing(): void
    {
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
            'endpoint_url' => null,
        ]);

        $transport = app(WebSocketClientTransport::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('WebSocket endpoint URL is required for client delivery.');

        $transport->deliver($channel, $thread, $post, []);
    }

    public function test_websocket_connection_can_be_created(): void
    {
        // This tests that the WebSocketConnection class can be instantiated
        // and has the correct interface without actually connecting
        $connection = new WebSocketConnection(
            id: 'test-connection',
            url: 'wss://echo.websocket.org/',
            options: [
                'timeout' => 10,
            ]
        );

        $this->assertSame('test-connection', $connection->getId());
        $this->assertSame('wss://echo.websocket.org/', $connection->getUrl());
        $this->assertFalse($connection->isConnected());
    }
}
