<?php

namespace App\Features\Actions\Chat;

use App\Features\Actions\Chat\Contracts\InboundMessageReceiver;

class InboundMessageReceiverResolver
{
    public function __construct(protected ProtocolRegistry $protocolRegistry) {}

    public function forProtocolTransport(string $protocol, string $transport): ?InboundMessageReceiver
    {
        return $this->protocolRegistry->inboundReceiver($protocol, $transport);
    }
}
