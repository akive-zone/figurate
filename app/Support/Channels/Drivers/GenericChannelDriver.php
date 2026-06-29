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
        return Channel::ProtocolGeneric;
    }

    public function supportedTransports(): array
    {
        return [
            Channel::TransportHttp,
            Channel::TransportWebhook,
            Channel::TransportWebsocket,
            Channel::TransportWebrtc,
            Channel::TransportRelay,
            Channel::TransportStdio,
        ];
    }

    public function supportedProtocols(): array
    {
        return [Channel::ProtocolGeneric];
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
     * @param  array<string, mixed>  $deliveryConfig
     * @return array<string, mixed>
     */
    public function send(Channel $channel, Thread $thread, Post $message, array $deliveryConfig = []): array
    {
        $transport = $this->resolveTransport($channel, $deliveryConfig);

        if (in_array($transport, ['http', 'webhook'], true)) {
            return $this->sendViaHttp($channel, $thread, $message, $deliveryConfig);
        }

        $outboundPayload = is_array($deliveryConfig['outbound_payload'] ?? null)
            ? $deliveryConfig['outbound_payload']
            : $this->fallbackOutboundPayload($channel, $thread, $message, $deliveryConfig);

        return [
            'status' => 'queued',
            'provider' => $channel->driver,
            'provider_message_id' => (string) Str::uuid(),
            'provider_identifier' => data_get($deliveryConfig, 'address.target') ?? data_get($deliveryConfig, 'target'),
            'thread_uuid' => $thread->uuid,
            'message_id' => $message->id,
            'payload' => $outboundPayload,
            'transport' => $transport,
        ];
    }

    /**
     * @param  array<string, mixed>  $deliveryConfig
     * @return array<string, mixed>
     */
    protected function sendViaHttp(Channel $channel, Thread $thread, Post $message, array $deliveryConfig): array
    {
        $transport = app(WebhookTransport::class);

        return $transport->deliver($channel, $thread, $message, $deliveryConfig);
    }

    protected function resolveTransport(Channel $channel, array $deliveryConfig): string
    {
        $bindingTransport = data_get($deliveryConfig, 'route.config.outbound.transport')
            ?? data_get($deliveryConfig, 'route.config.transport')
            ?? data_get($deliveryConfig, 'config.outbound.transport')
            ?? data_get($deliveryConfig, 'config.transport')
            ?? data_get($deliveryConfig, 'transport');

        if (is_string($bindingTransport) && trim($bindingTransport) !== '') {
            return strtolower(trim($bindingTransport));
        }

        if (is_string($channel->transport) && trim($channel->transport) !== '') {
            return strtolower(trim($channel->transport));
        }

        return Channel::TransportHttp;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizeInbound(Channel $channel, array $payload): array
    {
        $providerIdentifier = $payload['provider_identifier']
            ?? $payload['chat_id']
            ?? $payload['chatId']
            ?? $payload['target']
            ?? $payload['thread_id']
            ?? $payload['threadUuid']
            ?? null;

        return [
            'provider' => is_string($payload['provider'] ?? null) ? strtolower(trim((string) $payload['provider'])) : $channel->driver,
            'channel_uuid' => $channel->uuid,
            'provider_message_id' => $payload['provider_message_id'] ?? data_get($payload, 'message.id') ?? ($payload['id'] ?? null),
            'provider_identifier' => is_string($providerIdentifier) ? trim($providerIdentifier) : null,
            'target' => is_string($providerIdentifier) ? trim($providerIdentifier) : null,
            'target_type' => is_string($payload['target_type'] ?? null) ? trim((string) $payload['target_type']) : null,
            'sender' => $payload['sender'] ?? $payload['from'] ?? $payload['actor'] ?? null,
            'text' => is_string($payload['text'] ?? null)
                ? $payload['text']
                : (is_string($payload['message'] ?? null)
                    ? $payload['message']
                    : (is_string($payload['content'] ?? null) ? $payload['content'] : (is_string(data_get($payload, 'message.text')) ? data_get($payload, 'message.text') : ''))),
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
     * @param  array<string, mixed>  $deliveryConfig
     * @return array<string, mixed>
     */
    protected function fallbackOutboundPayload(Channel $channel, Thread $thread, Post $message, array $deliveryConfig): array
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
            'address' => [
                'provider_identifier' => data_get($deliveryConfig, 'address.target') ?? data_get($deliveryConfig, 'target'),
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
        $transport = strtolower(trim((string) ($attributes['transport'] ?? Channel::TransportHttp)));
        $direction = strtolower(trim((string) ($attributes['direction'] ?? Channel::DirectionBidirectional)));

        return array_merge($attributes, [
            'transport' => $transport !== '' ? $transport : Channel::TransportHttp,
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
            $normalized['transport'] = $transport !== '' ? $transport : Channel::TransportHttp;
        }

        if (array_key_exists('direction', $normalized)) {
            $direction = strtolower(trim((string) $normalized['direction']));
            $normalized['direction'] = $direction !== '' ? $direction : Channel::DirectionBidirectional;
        }

        return $normalized;
    }
}
