<?php

namespace Tests\Feature\Channels;

use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use App\Support\Channels\Transports\WebhookTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\WebhookServer\CallWebhookJob;
use Tests\TestCase;

class WebhookTransportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_webhook_delivery_job_with_spatie_webhook_server(): void
    {
        Queue::fake();

        $sender = User::factory()->create();
        $space = Space::factory()->create();
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Webhook Test Thread',
            'phase' => 'execution',
            'status' => 'open',
        ]);
        $post = $thread->posts()->create([
            'type' => Post::TypeMessage,
            'status' => Post::StatusActive,
            'data' => [
                'text' => 'Test webhook delivery',
                'message_type' => 'text',
            ],
            'meta' => [
                'source' => 'test',
            ],
        ]);
        $post->attachRelation($sender, Post::RelationRoleSender);

        $channel = Channel::factory()->create([
            'driver' => 'webhook',
            'endpoint_url' => 'https://example.com/webhook',
            'credentials' => [
                'secret' => 'test-secret-key',
                'headers' => [
                    'X-Custom-Header' => 'custom-value',
                ],
            ],
        ]);

        $bindingConfig = [
            'provider_identifier' => 'test-webhook-123',
            'outbound_payload' => [
                'event' => 'thread.post.created',
                'thread' => [
                    'uuid' => $thread->uuid,
                ],
                'post' => [
                    'ulid' => $post->ulid,
                    'text' => 'Test webhook delivery',
                ],
            ],
        ];

        $transport = app(WebhookTransport::class);
        $result = $transport->deliver($channel, $thread, $post, $bindingConfig);

        $this->assertSame('dispatched', $result['status']);
        $this->assertSame('webhook', $result['provider']);
        $this->assertSame('https://example.com/webhook', $result['endpoint_url']);
        $this->assertTrue((bool) $result['signed']);
        $this->assertSame('test-webhook-123', $result['provider_identifier']);
        $this->assertArrayHasKey('payload', $result);
        $this->assertSame('thread.post.created', $result['payload']['event']);

        Queue::assertPushed(CallWebhookJob::class, function (CallWebhookJob $job): bool {
            return $job->webhookUrl === 'https://example.com/webhook';
        });
    }

    public function test_it_dispatches_unsigned_webhooks_when_no_secret_configured(): void
    {
        Queue::fake();

        $sender = User::factory()->create();
        $space = Space::factory()->create();
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Unsigned Webhook Test',
            'phase' => 'execution',
            'status' => 'open',
        ]);
        $post = $thread->posts()->create([
            'type' => Post::TypeMessage,
            'status' => Post::StatusActive,
            'data' => [
                'text' => 'Test unsigned webhook',
                'message_type' => 'text',
            ],
        ]);
        $post->attachRelation($sender, Post::RelationRoleSender);

        $channel = Channel::factory()->create([
            'driver' => 'webhook',
            'endpoint_url' => 'https://example.com/webhook',
            'credentials' => [],
        ]);

        $bindingConfig = [
            'outbound_payload' => [
                'event' => 'thread.post.created',
                'post' => ['text' => 'Test unsigned webhook'],
            ],
        ];

        $transport = app(WebhookTransport::class);
        $result = $transport->deliver($channel, $thread, $post, $bindingConfig);

        $this->assertSame('dispatched', $result['status']);
        $this->assertFalse((bool) $result['signed']);

        Queue::assertPushed(CallWebhookJob::class);
    }

    public function test_it_overrides_endpoint_url_from_binding_config(): void
    {
        Queue::fake();

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
            'driver' => 'webhook',
            'endpoint_url' => 'https://example.com/default',
        ]);

        $bindingConfig = [
            'outbound_payload' => ['event' => 'test'],
            'config' => [
                'endpoint_url' => 'https://example.com/override',
            ],
        ];

        $transport = app(WebhookTransport::class);
        $result = $transport->deliver($channel, $thread, $post, $bindingConfig);

        $this->assertSame('https://example.com/override', $result['endpoint_url']);

        Queue::assertPushed(CallWebhookJob::class, function (CallWebhookJob $job): bool {
            return $job->webhookUrl === 'https://example.com/override';
        });
    }

    public function test_it_throws_exception_when_endpoint_url_is_missing(): void
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
            'driver' => 'webhook',
            'endpoint_url' => null,
        ]);

        $transport = app(WebhookTransport::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Webhook endpoint URL is required for delivery.');

        $transport->deliver($channel, $thread, $post, []);
    }
}
