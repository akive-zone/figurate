<?php

namespace App\Support\Channels\Drivers;

use App\Models\Server\Channel;

class WebhookChannelDriver extends GenericChannelDriver
{
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
        return ['event.receive', 'post.receive', 'receipt.receive'];
    }

    public function prepareForRegistration(array $attributes): array
    {
        return array_merge(parent::prepareForRegistration($attributes), [
            'direction' => $attributes['direction'] ?? Channel::DirectionInbound,
        ]);
    }
}
