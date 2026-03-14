<?php

namespace App\Actions\Server\Chat\Contracts;

use App\Actions\Server\Chat\InboundMessageEnvelope;
use App\Models\Server\Message;

interface InboundMessageReceiver
{
    public function receive(InboundMessageEnvelope $envelope): Message;
}
