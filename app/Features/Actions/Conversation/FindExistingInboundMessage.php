<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Message;
use App\Models\Server\Outbox;

class FindExistingInboundMessage
{
    public function execute(string $idempotencyKey): ?Message
    {
        $existingInbound = Outbox::query()
            ->where('direction', Outbox::DirectionInbound)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (! $existingInbound?->message_id) {
            return null;
        }

        return Message::query()->find($existingInbound->message_id);
    }
}
