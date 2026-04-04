<?php

namespace App\Support\Channels\Drivers;

use App\Models\Server\Channel;

class WebsocketChannelDriver extends GenericChannelDriver
{
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
        return ['post.send', 'post.receive', 'event.stream', 'presence'];
    }

    public function prepareForRegistration(array $attributes): array
    {
        return array_merge(parent::prepareForRegistration($attributes), [
            'direction' => $attributes['direction'] ?? Channel::DirectionBidirectional,
        ]);
    }
}
