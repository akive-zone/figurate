<?php

namespace App\Features\Actions\Conversation;

use App\Features\Actions\Conversation\Contracts\InboundMessageReceiver;
use App\Features\Operations\Chat\IngestInboundChatMessageOperation;
use App\Models\Server\Message;

class NostrRelayInboundMessageReceiver implements InboundMessageReceiver
{
    public function __construct(protected IngestInboundChatMessageOperation $ingestInboundChatMessageOperation) {}

    public function receive(InboundMessageEnvelope $envelope): Message
    {
        return $this->ingestInboundChatMessageOperation->run($envelope);
    }
}
