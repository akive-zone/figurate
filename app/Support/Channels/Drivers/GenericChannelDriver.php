<?php

namespace App\Support\Channels\Drivers;

use App\Contracts\Channels\ChannelDriver;
use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Support\Channels\Transports\WebhookTransport;
use Illuminate\Support\Str;

class GenericChannelDriver implements ChannelDriver
{
    public function key(): string
    {
        return Channel::DriverGeneric;
    }

    public function supportedTransports(): array
    {
        return ['http', 'webhook', 'websocket', 'webrtc', 'relay', 'stdio'];
    }

    public function supportedProtocols(): array
    {
        return [
            Channel::ProtocolMcp,
            Channel::ProtocolA2a,
            Channel::ProtocolAcp,
        ];
    }

    public function capabilities(?Channel $channel = null): array
    {
        return ['post.send', 'post.receive', 'receipt.receive'];
    }

    public function prepareForRegistration(array $attributes): array
    {
        return $this->normalizeRegistrationAttributes($attributes);
    }

    public function prepareForUpdate(Channel $channel, array $attributes): array
    {
        return $this->normalizeUpdateAttributes($attributes);
    }

    /**
     * @param  array<string, mixed>  $bindingConfig
     * @return array<string, mixed>
     */
    public function send(Channel $channel, Thread $thread, Post $message, array $bindingConfig = []): array
    {
        $transport = $this->resolveTransport($channel, $bindingConfig);

        if (in_array($transport, ['http', 'webhook'], true)) {
            return $this->sendViaHttp($channel, $thread, $message, $bindingConfig);
        }

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
            'transport' => $transport,
        ];
    }

    /**
     * @param  array<string, mixed>  $bindingConfig
     * @return array<string, mixed>
     */
    protected function sendViaHttp(Channel $channel, Thread $thread, Post $message, array $bindingConfig): array
    {
        $transport = app(WebhookTransport::class);

        return $transport->deliver($channel, $thread, $message, $bindingConfig);
    }

    protected function resolveTransport(Channel $channel, array $bindingConfig): string
    {
        $bindingTransport = data_get($bindingConfig, 'delivery.binding.config.transport')
            ?? data_get($bindingConfig, 'config.transport')
            ?? data_get($bindingConfig, 'transport');

        if (is_string($bindingTransport) && trim($bindingTransport) !== '') {
            return strtolower(trim($bindingTransport));
        }

        if (is_string($channel->transport) && trim($channel->transport) !== '') {
            return strtolower(trim($channel->transport));
        }

        return 'remote';
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

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function normalizeRegistrationAttributes(array $attributes): array
    {
        $transport = strtolower(trim((string) ($attributes['transport'] ?? 'remote')));
        $direction = strtolower(trim((string) ($attributes['direction'] ?? Channel::DirectionBidirectional)));

        return array_merge($attributes, [
            'transport' => $transport !== '' ? $transport : 'remote',
            'direction' => $direction !== '' ? $direction : Channel::DirectionBidirectional,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function normalizeUpdateAttributes(array $attributes): array
    {
        $normalized = $attributes;

        if (array_key_exists('transport', $normalized)) {
            $transport = strtolower(trim((string) $normalized['transport']));
            $normalized['transport'] = $transport !== '' ? $transport : 'remote';
        }

        if (array_key_exists('direction', $normalized)) {
            $direction = strtolower(trim((string) $normalized['direction']));
            $normalized['direction'] = $direction !== '' ? $direction : Channel::DirectionBidirectional;
        }

        return $normalized;
    }
}
