<?php

namespace App\Support\Channels\Transports;

use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use Spatie\WebhookServer\WebhookCall;

class WebhookTransport
{
    /**
     * @param  array<string, mixed>  $deliveryConfig
     * @return array<string, mixed>
     */
    public function deliver(Channel $channel, Thread $thread, Post $message, array $deliveryConfig = []): array
    {
        $outboundPayload = is_array($deliveryConfig['outbound_payload'] ?? null)
            ? $deliveryConfig['outbound_payload']
            : [];

        $endpointUrl = $this->resolveEndpointUrl($channel, $deliveryConfig);

        if ($endpointUrl === null || $endpointUrl === '') {
            throw new \RuntimeException('Webhook endpoint URL is required for delivery.');
        }

        $call = WebhookCall::create()
            ->url($endpointUrl)
            ->payload($outboundPayload)
            ->useTimestamp();

        $headers = $this->resolveHeaders($channel, $deliveryConfig);
        if ($headers !== []) {
            $call = $call->withHeaders($headers);
        }

        $secret = $this->resolveSecret($channel, $deliveryConfig);
        if ($secret !== null && $secret !== '') {
            $call = $call->useSecret($secret);
        } else {
            $call = $call->doNotSign();
        }

        $meta = $this->resolveMeta($channel, $thread, $message, $deliveryConfig);
        if ($meta !== []) {
            $call = $call->meta($meta);
        }

        $call->dispatch();

        return [
            'status' => 'dispatched',
            'provider' => $channel->driver,
            'provider_message_id' => $this->generateMessageId($channel, $thread, $message),
            'provider_identifier' => data_get($deliveryConfig, 'address.target')
                ?? data_get($deliveryConfig, 'target')
                ?? data_get($deliveryConfig, 'provider_identifier'),
            'thread_uuid' => $thread->uuid,
            'message_id' => $message->id,
            'endpoint_url' => $endpointUrl,
            'signed' => $secret !== null && $secret !== '',
            'payload' => $outboundPayload,
        ];
    }

    protected function resolveEndpointUrl(Channel $channel, array $deliveryConfig): ?string
    {
        $bindingEndpoint = data_get($deliveryConfig, 'route.config.outbound.endpoint_url')
            ?? data_get($deliveryConfig, 'route.config.endpoint_url')
            ?? data_get($deliveryConfig, 'config.outbound.endpoint_url')
            ?? data_get($deliveryConfig, 'config.endpoint_url')
            ?? data_get($deliveryConfig, 'endpoint_url');

        if (is_string($bindingEndpoint) && trim($bindingEndpoint) !== '') {
            return trim($bindingEndpoint);
        }

        if (is_string($channel->endpoint_url) && trim($channel->endpoint_url) !== '') {
            return trim($channel->endpoint_url);
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function resolveHeaders(Channel $channel, array $deliveryConfig): array
    {
        $channelHeaders = [];
        if (is_array($channel->credentials)) {
            $channelHeaders = is_array($channel->credentials['headers'] ?? null)
                ? $channel->credentials['headers']
                : [];
        }

        $bindingHeaders = data_get($deliveryConfig, 'route.config.outbound.credentials.headers')
            ?? data_get($deliveryConfig, 'route.config.credentials.headers')
            ?? data_get($deliveryConfig, 'config.outbound.credentials.headers')
            ?? data_get($deliveryConfig, 'config.credentials.headers')
            ?? data_get($deliveryConfig, 'credentials.headers')
            ?? [];

        $bindingHeaders = is_array($bindingHeaders) ? $bindingHeaders : [];

        return collect($channelHeaders)
            ->merge($bindingHeaders)
            ->filter(fn (mixed $value, mixed $key): bool => is_string($key) && is_string($value))
            ->all();
    }

    protected function resolveSecret(Channel $channel, array $deliveryConfig): ?string
    {
        $bindingSecret = data_get($deliveryConfig, 'route.config.outbound.credentials.secret')
            ?? data_get($deliveryConfig, 'route.config.credentials.secret')
            ?? data_get($deliveryConfig, 'config.outbound.credentials.secret')
            ?? data_get($deliveryConfig, 'config.credentials.secret')
            ?? data_get($deliveryConfig, 'credentials.secret');

        if (is_string($bindingSecret) && trim($bindingSecret) !== '') {
            return trim($bindingSecret);
        }

        if (is_array($channel->credentials)) {
            $channelSecret = $channel->credentials['secret'] ?? null;
            if (is_string($channelSecret) && trim($channelSecret) !== '') {
                return trim($channelSecret);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveMeta(Channel $channel, Thread $thread, Post $message, array $deliveryConfig): array
    {
        return [
            'channel_id' => $channel->id,
            'channel_uuid' => $channel->uuid,
            'thread_id' => $thread->id,
            'thread_uuid' => $thread->uuid,
            'message_id' => $message->id,
            'message_ulid' => $message->ulid,
            'provider_identifier' => data_get($deliveryConfig, 'address.target')
                ?? data_get($deliveryConfig, 'target')
                ?? data_get($deliveryConfig, 'provider_identifier'),
            'route_id' => data_get($deliveryConfig, 'route.id'),
            'address_id' => data_get($deliveryConfig, 'address.id'),
        ];
    }

    protected function generateMessageId(Channel $channel, Thread $thread, Post $message): string
    {
        return sprintf(
            'webhook:%s:%s:%s',
            $channel->uuid,
            $thread->uuid,
            $message->ulid
        );
    }
}
