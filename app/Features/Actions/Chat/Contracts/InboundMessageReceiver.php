<?php

namespace App\Features\Actions\Chat\Contracts;

use App\Features\Actions\Chat\InboundMessageEnvelope;
use App\Models\Server\Post;

interface InboundMessageReceiver
{
    public function receive(InboundMessageEnvelope $envelope): Post;
}
