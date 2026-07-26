<?php

namespace Figurate\LegacyProtocols\Conversation;

use App\Features\Actions\Chat\Contracts\InboundMessageReceiver;
use App\Features\Actions\Chat\InboundMessageEnvelope;
use App\Features\Operations\Chat\IngestInboundChatMessageOperation;
use App\Models\Server\Post;

class NostrRelayInboundMessageReceiver implements InboundMessageReceiver
{
    public function __construct(protected IngestInboundChatMessageOperation $ingestInboundChatMessageOperation) {}

    public function receive(InboundMessageEnvelope $envelope): Post
    {
        return $this->ingestInboundChatMessageOperation->run($envelope);
    }
}
