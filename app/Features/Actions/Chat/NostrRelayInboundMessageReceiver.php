<?php

namespace App\Features\Actions\Chat;

use App\Features\Actions\Chat\Contracts\InboundMessageReceiver;
use App\Models\Server\Message;

class NostrRelayInboundMessageReceiver implements InboundMessageReceiver
{
    public function __construct(protected IngestInboundMessage $ingestInboundMessage) {}

    public function receive(InboundMessageEnvelope $envelope): Message
    {
        return ($this->ingestInboundMessage)($envelope);
    }
}
