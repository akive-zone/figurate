<?php

namespace App\Features\Actions\Chat;

use App\Features\Actions\Chat\Contracts\InboundMessageReceiver;
use App\Features\Operations\Chat\IngestInboundChatMessageOperation;
use App\Models\Server\Message;

class HttpInboundMessageReceiver implements InboundMessageReceiver
{
    public function __construct(protected IngestInboundChatMessageOperation $ingestInboundChatMessageOperation) {}

    public function receive(InboundMessageEnvelope $envelope): Message
    {
        return $this->ingestInboundChatMessageOperation->run($envelope);
    }
}
