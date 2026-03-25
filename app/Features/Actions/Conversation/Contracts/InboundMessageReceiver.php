<?php

namespace App\Features\Actions\Conversation\Contracts;

use App\Features\Actions\Conversation\InboundMessageEnvelope;
use App\Models\Server\Post;

interface InboundMessageReceiver
{
    public function receive(InboundMessageEnvelope $envelope): Post;
}
