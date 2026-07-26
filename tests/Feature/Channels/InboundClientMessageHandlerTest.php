<?php

namespace Tests\Feature\Channels;

use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use App\Support\Channels\WebSocket\InboundClientMessageHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundClientMessageHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_processes_messages_from_external_websocket_servers(): void
    {
        $channel = Channel::factory()->create([
            'driver' => 'websocket',
            'name' => 'External WS Server',
            'direction' => Channel::DirectionInbound,
            'endpoint_url' => 'wss://external-server.com/ws',
        ]);

        $sender = User::factory()->create();
        $space = Space::factory()->create();
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'WebSocket Client Listener Test',
            'phase' => 'execution',
            'status' => 'open',
        ]);

        // Associate channel with thread
        $channel->relations()->create([
            'relationable_type' => Thread::class,
            'relationable_id' => $thread->id,
            'kind' => 'link',
        ]);

        $messageData = [
            'id' => 'msg-123',
            'type' => 'message',
            'thread_uuid' => $thread->uuid,
            'sender' => $sender->uuid,
            'text' => 'Hello from external server!',
            'timestamp' => now()->toIso8601String(),
        ];

        $handler = app(InboundClientMessageHandler::class);
        $result = $handler->handle($channel, $messageData);

        $this->assertSame('success', $result['status']);
        $this->assertSame('msg-123', $result['external_message_id']);
        $this->assertSame($thread->uuid, $result['thread_uuid']);
        $this->assertArrayHasKey('post_id', $result);
        $this->assertArrayHasKey('post_ulid', $result);

        // Verify post was created
        $post = Post::query()->where('id', $result['post_id'])->first();
        $this->assertNotNull($post);
        $this->assertSame('Hello from external server!', $post->text);
        $this->assertSame(Post::TypeMessage, $post->type);
        $this->assertSame('websocket_client_inbound', $post->meta['source']);
        $this->assertSame($channel->id, $post->meta['channel_id']);

        // Verify sender relation
        $this->assertTrue($post->relations()->where('relationable_id', $sender->id)->exists());
    }

    public function test_it_handles_messages_without_explicit_thread(): void
    {
        $channel = Channel::factory()->create([
            'driver' => 'websocket',
            'endpoint_url' => 'wss://external-server.com/ws',
        ]);

        $space = Space::factory()->create();
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'phase' => 'execution',
            'status' => 'open',
        ]);

        // Associate channel with thread so it can be found
        $channel->relations()->create([
            'relationable_type' => Thread::class,
            'relationable_id' => $thread->id,
            'kind' => 'link',
        ]);

        $messageData = [
            'text' => 'Message without explicit thread',
            'type' => 'message',
        ];

        $handler = app(InboundClientMessageHandler::class);
        $result = $handler->handle($channel, $messageData);

        $this->assertSame('success', $result['status']);
        $this->assertSame($thread->uuid, $result['thread_uuid']);
    }

    public function test_it_rejects_messages_without_content(): void
    {
        $channel = Channel::factory()->create([
            'driver' => 'websocket',
        ]);

        $messageData = [
            'type' => 'message',
            // Missing text/message/content
        ];

        $handler = app(InboundClientMessageHandler::class);
        $result = $handler->handle($channel, $messageData);

        $this->assertSame('error', $result['status']);
        $this->assertSame('Missing message content', $result['error']);
    }

    public function test_it_rejects_empty_messages(): void
    {
        $channel = Channel::factory()->create([
            'driver' => 'websocket',
        ]);

        $handler = app(InboundClientMessageHandler::class);
        $result = $handler->handle($channel, []);

        $this->assertSame('error', $result['status']);
        $this->assertSame('Empty message received', $result['error']);
    }

    public function test_it_handles_different_message_field_names(): void
    {
        $channel = Channel::factory()->create([
            'driver' => 'websocket',
        ]);

        $space = Space::factory()->create();
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'phase' => 'execution',
            'status' => 'open',
        ]);

        $channel->relations()->create([
            'relationable_type' => Thread::class,
            'relationable_id' => $thread->id,
            'kind' => 'link',
        ]);

        // Test with 'content' instead of 'text'
        $messageData = [
            'content' => 'Message with content field',
            'event' => 'chat',
        ];

        $handler = app(InboundClientMessageHandler::class);
        $result = $handler->handle($channel, $messageData);

        $this->assertSame('success', $result['status']);

        $post = Post::query()->where('id', $result['post_id'])->first();
        $this->assertSame('Message with content field', $post->text);
    }

    public function test_it_normalizes_post_types(): void
    {
        $channel = Channel::factory()->create(['driver' => 'websocket']);
        $space = Space::factory()->create();
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'phase' => 'execution',
            'status' => 'open',
        ]);

        $channel->relations()->create([
            'relationable_type' => Thread::class,
            'relationable_id' => $thread->id,
            'kind' => 'link',
        ]);
        $handler = app(InboundClientMessageHandler::class);

        // Test message type
        $result = $handler->handle($channel, ['text' => 'Test', 'type' => 'text']);
        $post = Post::find($result['post_id']);
        $this->assertSame(Post::TypeMessage, $post->type);

        // Test system type (currently all types map to TypeMessage)
        $result = $handler->handle($channel, ['text' => 'Test', 'type' => 'system']);
        $post = Post::find($result['post_id']);
        $this->assertSame(Post::TypeMessage, $post->type);

        // Test notification type
        $result = $handler->handle($channel, ['text' => 'Test', 'type' => 'notification']);
        $post = Post::find($result['post_id']);
        $this->assertSame(Post::TypeMessage, $post->type);
    }

    public function test_it_returns_error_when_no_thread_found(): void
    {
        $channel = Channel::factory()->create([
            'driver' => 'websocket',
        ]);

        $messageData = [
            'text' => 'Test message',
            'thread_uuid' => 'nonexistent-uuid',
        ];

        $handler = app(InboundClientMessageHandler::class);
        $result = $handler->handle($channel, $messageData);

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('Thread not found', $result['error']);
    }
}
