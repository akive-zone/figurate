<?php

namespace App\Support\Channels\Transports;

use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Support\Channels\WebSocket\WebSocketConnectionManager;

/**
 * WebSocket Client Transport - Sends messages to remote WebSocket servers
 *
 * Direction: Outbound (Figurate → Remote WS Server)
 * Mode: Client (Figurate connects to external WebSocket servers)
 */
class WebSocketClientTransport
{
    public function __construct(
        protected WebSocketConnectionManager $connectionManager,
    ) {}

    /**
     * @param  array<string, mixed>  $bindingConfig
     * @return array<string, mixed>
     */
    public function deliver(Channel $channel, Thread $thread, Post $message, array $bindingConfig = []): array
    {
        $outboundPayload = is_array($bindingConfig['outbound_payload'] ?? null)
            ? $bindingConfig['outbound_payload']
            : [];

        $endpointUrl = $this->resolveEndpointUrl($channel, $bindingConfig);

        if ($endpointUrl === null || $endpointUrl === '') {
            throw new \RuntimeException('WebSocket endpoint URL is required for client delivery.');
        }

        // Get or create connection to the remote WebSocket server
        $connection = $this->connectionManager->getOrCreateConnection($channel, $endpointUrl, $bindingConfig);

        // Send message over the WebSocket connection
        $connection->send(json_encode($outboundPayload));

        return [
            'status' => 'sent',
            'provider' => $channel->driver,
            'provider_message_id' => $this->generateProviderMessageId($channel, $thread, $message),
            'provider_identifier' => $bindingConfig['provider_identifier'] ?? null,
            'thread_uuid' => $thread->uuid,
            'post_id' => $message->id,
            'endpoint_url' => $endpointUrl,
            'transport' => 'websocket-client',
            'mode' => 'client',
            'connection_id' => $connection->getId(),
            'payload' => $outboundPayload,
        ];
    }

    protected function resolveEndpointUrl(Channel $channel, array $bindingConfig): ?string
    {
        $bindingEndpoint = data_get($bindingConfig, 'delivery.binding.config.endpoint_url')
            ?? data_get($bindingConfig, 'config.endpoint_url')
            ?? data_get($bindingConfig, 'endpoint_url');

        if (is_string($bindingEndpoint) && trim($bindingEndpoint) !== '') {
            return trim($bindingEndpoint);
        }

        if (is_string($channel->endpoint_url) && trim($channel->endpoint_url) !== '') {
            return trim($channel->endpoint_url);
        }

        return null;
    }

    protected function generateProviderMessageId(Channel $channel, Thread $thread, Post $message): string
    {
        return sprintf(
            'ws-client:%s:%s:%s',
            $channel->uuid,
            $thread->uuid,
            $message->ulid
        );
    }
}
