<?php

namespace App\Actions\Server\Chat;

use App\Actions\Server\Chat\Contracts\OutboundMessageSender;
use App\Models\Server\Outbox;

class NostrOutboundMessageSender implements OutboundMessageSender
{
    /**
     * @return array<string, mixed>
     */
    public function send(Outbox $outbox): array
    {
        $target = is_string($outbox->target) ? trim($outbox->target) : '';

        if ($target === '') {
            throw new \RuntimeException('Nostr target is required.');
        }

        return [
            'ok' => true,
            'protocol' => 'nostr',
            'provider' => $outbox->provider,
            'target' => $target,
            'delivery' => 'queued_for_adapter',
            'message_id' => data_get($outbox->payload ?? [], 'message.id'),
        ];
    }
}
