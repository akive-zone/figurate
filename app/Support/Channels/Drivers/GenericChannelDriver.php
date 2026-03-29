<?php

namespace App\Support\Channels\Drivers;

use App\Contracts\Channels\ChannelDriver;
use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use Illuminate\Support\Str;

class GenericChannelDriver implements ChannelDriver
{
    /**
     * @param  array<string, mixed>  $bindingConfig
     * @return array<string, mixed>
     */
    public function send(Channel $channel, Thread $thread, Post $message, array $bindingConfig = []): array
    {
        $outboundPayload = is_array($bindingConfig['outbound_payload'] ?? null)
            ? $bindingConfig['outbound_payload']
            : $this->fallbackOutboundPayload($channel, $thread, $message, $bindingConfig);

        return [
            'status' => 'queued',
            'provider' => $channel->driver,
            'provider_message_id' => (string) Str::uuid(),
            'provider_identifier' => $bindingConfig['provider_identifier'] ?? null,
            'thread_uuid' => $thread->uuid,
            'message_id' => $message->id,
            'payload' => $outboundPayload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizeInbound(Channel $channel, array $payload): array
    {
        return [
            'provider' => $channel->driver,
            'channel_uuid' => $channel->uuid,
            'provider_message_id' => $payload['provider_message_id'] ?? null,
            'provider_identifier' => $payload['provider_identifier'] ?? null,
            'sender' => $payload['sender'] ?? null,
            'text' => is_string($payload['text'] ?? null) ? $payload['text'] : '',
            'raw' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizeReceipt(Channel $channel, array $payload): array
    {
        return [
            'provider' => $channel->driver,
            'channel_uuid' => $channel->uuid,
            'provider_message_id' => $payload['provider_message_id'] ?? null,
            'provider_identifier' => $payload['provider_identifier'] ?? null,
            'status' => $payload['status'] ?? 'unknown',
            'occurred_at' => $payload['occurred_at'] ?? null,
            'raw' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $bindingConfig
     * @return array<string, mixed>
     */
    protected function fallbackOutboundPayload(Channel $channel, Thread $thread, Post $message, array $bindingConfig): array
    {
        return [
            'event' => 'thread.post.created',
            'occurred_at' => optional($message->occurred_at ?? $message->created_at)?->toIso8601String(),
            'channel' => [
                'id' => $channel->id,
                'uuid' => $channel->uuid,
                'driver' => $channel->driver,
                'name' => $channel->name,
            ],
            'binding' => [
                'provider_identifier' => $bindingConfig['provider_identifier'] ?? null,
            ],
            'thread' => [
                'id' => $thread->id,
                'uuid' => $thread->uuid,
            ],
            'post' => [
                'id' => $message->id,
                'ulid' => $message->ulid,
                'text' => $message->text,
            ],
        ];
    }
}
