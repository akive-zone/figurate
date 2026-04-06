<?php

namespace App\Support\Channels\Drivers;

use App\Models\Server\Channel;

class AcpChannelDriver extends GenericChannelDriver
{
    public function key(): string
    {
        return Channel::DriverAcp;
    }

    public function supportedProtocols(): array
    {
        return [Channel::ProtocolAcp];
    }

    public function capabilities(?Channel $channel = null): array
    {
        return ['session.invoke', 'session.delegate', 'task.read', 'post.send', 'post.receive'];
    }
}
