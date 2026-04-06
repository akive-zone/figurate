<?php

namespace App\Support\Channels\Drivers;

use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Support\Channels\Transports\WebSocketClientTransport;
use App\Support\Channels\Transports\WebSocketServerTransport;

class WebsocketChannelDriver extends GenericChannelDriver
{
    public function __construct(
        protected WebSocketServerTransport $serverTransport,
        protected WebSocketClientTransport $clientTransport,
    ) {}

    public function key(): string
    {
        return 'websocket';
    }

    public function supportedTransports(): array
    {
        return ['websocket'];
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
        return ['post.send', 'post.receive', 'event.stream', 'presence', 'stream.bidirectional'];
    }

    public function prepareForRegistration(array $attributes): array
    {
        return array_merge(parent::prepareForRegistration($attributes), [
            'direction' => $attributes['direction'] ?? Channel::DirectionBidirectional,
        ]);
    }

    /**
     * @param  array<string, mixed>  $bindingConfig
     * @return array<string, mixed>
     */
    public function send(Channel $channel, Thread $thread, Post $message, array $bindingConfig = []): array
    {
        $mode = $this->resolveMode($channel, $bindingConfig);

        if ($mode === 'client') {
            return $this->clientTransport->deliver($channel, $thread, $message, $bindingConfig);
        }

        if ($mode === 'server') {
            return $this->serverTransport->deliver($channel, $thread, $message, $bindingConfig);
        }

        throw new \RuntimeException("Unsupported WebSocket mode [{$mode}]. Expected 'client' or 'server'.");
    }

    protected function resolveMode(Channel $channel, array $bindingConfig): string
    {
        // Check binding config first
        $bindingMode = data_get($bindingConfig, 'delivery.binding.config.mode')
            ?? data_get($bindingConfig, 'config.mode')
            ?? data_get($bindingConfig, 'mode');

        if (is_string($bindingMode) && in_array(strtolower(trim($bindingMode)), ['client', 'server'], true)) {
            return strtolower(trim($bindingMode));
        }

        // Check channel config/meta
        if (is_array($channel->config)) {
            $channelMode = $channel->config['mode'] ?? null;
            if (is_string($channelMode) && in_array(strtolower(trim($channelMode)), ['client', 'server'], true)) {
                return strtolower(trim($channelMode));
            }
        }

        // Default based on direction
        if ($channel->direction === Channel::DirectionOutbound) {
            return 'client'; // Outbound = connect to remote server
        }

        return 'server'; // Inbound/bidirectional = broadcast to connected clients
    }
}
