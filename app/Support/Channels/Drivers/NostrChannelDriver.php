<?php

namespace App\Support\Channels\Drivers;

use App\Models\Server\Channel;

class NostrChannelDriver extends GenericChannelDriver
{
    public function key(): string
    {
        return 'nostr';
    }

    public function supportedTransports(): array
    {
        return ['relay', 'websocket'];
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
        return ['post.send', 'post.receive', 'event.receive'];
    }

    public function prepareForRegistration(array $attributes): array
    {
        return array_merge(parent::prepareForRegistration($attributes), [
            'direction' => $attributes['direction'] ?? Channel::DirectionBidirectional,
        ]);
    }
}
