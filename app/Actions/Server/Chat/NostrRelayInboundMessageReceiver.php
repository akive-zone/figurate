<?php

namespace App\Actions\Server\Chat;

use App\Actions\Server\Chat\Contracts\InboundMessageReceiver;
use App\Models\Server\Message;

class NostrRelayInboundMessageReceiver implements InboundMessageReceiver
{
    public function __construct(protected IngestInboundMessage $ingestInboundMessage) {}

    public function receive(InboundMessageEnvelope $envelope): Message
    {
        return ($this->ingestInboundMessage)($envelope);
    }
}
