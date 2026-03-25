<?php

namespace App\Features\Actions\Conversation\Contracts;

use App\Models\Server\Outbox;

interface OutboundMessageSender
{
    /**
     * @return array<string, mixed>
     */
    public function send(Outbox $outbox): array;
}
