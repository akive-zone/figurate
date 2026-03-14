<?php

namespace App\Actions\Server\Chat;

use App\Actions\Server\Chat\Contracts\InboundMessageReceiver;

class InboundMessageReceiverResolver
{
    public function __construct(protected ChatProtocolRegistry $chatProtocolRegistry) {}

    public function forProtocolTransport(string $protocol, string $transport): ?InboundMessageReceiver
    {
        return $this->chatProtocolRegistry->inboundReceiver($protocol, $transport);
    }
}
