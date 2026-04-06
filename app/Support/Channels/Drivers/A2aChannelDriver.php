<?php

namespace App\Support\Channels\Drivers;

use App\Models\Server\Channel;

class A2aChannelDriver extends GenericChannelDriver
{
    public function key(): string
    {
        return Channel::ProtocolA2a;
    }

    public function supportedTransports(): array
    {
        return [Channel::TransportHttp, Channel::TransportWebsocket, Channel::TransportStdio];
    }

    public function supportedProtocols(): array
    {
        return [Channel::ProtocolA2a];
    }

    public function capabilities(?Channel $channel = null): array
    {
        return ['agent.invoke', 'task.delegate', 'task.read', 'post.send', 'post.receive'];
    }
}
