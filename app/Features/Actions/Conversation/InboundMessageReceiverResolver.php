<?php

namespace App\Features\Actions\Conversation;

use App\Features\Actions\Conversation\Contracts\InboundMessageReceiver;

class InboundMessageReceiverResolver
{
    public function __construct(protected ProtocolRegistry $protocolRegistry) {}

    public function forProtocolTransport(string $protocol, string $transport): ?InboundMessageReceiver
    {
        return $this->protocolRegistry->inboundReceiver($protocol, $transport);
    }
}
