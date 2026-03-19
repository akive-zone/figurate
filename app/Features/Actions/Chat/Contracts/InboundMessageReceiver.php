<?php

namespace App\Features\Actions\Chat\Contracts;

use App\Features\Actions\Chat\InboundMessageEnvelope;
use App\Models\Server\Message;

interface InboundMessageReceiver
{
    public function receive(InboundMessageEnvelope $envelope): Message;
}
