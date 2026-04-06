<?php

namespace App\Support\Channels\Drivers;

use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Support\Channels\Transports\WebhookTransport;

class WebhookChannelDriver extends GenericChannelDriver
{
    public function __construct(
        protected WebhookTransport $webhookTransport,
    ) {}

    public function key(): string
    {
        return 'webhook';
    }

    public function supportedTransports(): array
    {
        return ['webhook', 'http'];
    }

    public function supportedProtocols(): array
    {
        return [
            Channel::ProtocolA2a,
            Channel::ProtocolAcp,
        ];
    }

    public function capabilities(?Channel $channel = null): array
    {
        return ['event.receive', 'post.receive', 'receipt.receive', 'post.send'];
    }

    public function prepareForRegistration(array $attributes): array
    {
        return array_merge(parent::prepareForRegistration($attributes), [
            'direction' => $attributes['direction'] ?? Channel::DirectionInbound,
        ]);
    }

    /**
     * @param  array<string, mixed>  $bindingConfig
     * @return array<string, mixed>
     */
    public function send(Channel $channel, Thread $thread, Post $message, array $bindingConfig = []): array
    {
        return $this->webhookTransport->deliver($channel, $thread, $message, $bindingConfig);
    }
}
