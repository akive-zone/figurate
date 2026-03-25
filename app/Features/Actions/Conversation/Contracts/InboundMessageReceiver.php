<?php

namespace App\Features\Actions\Conversation\Contracts;

use App\Features\Actions\Conversation\InboundMessageEnvelope;
use App\Models\Server\Message;

interface InboundMessageReceiver
{
    public function receive(InboundMessageEnvelope $envelope): Message;
}
