<?php

namespace Figurate\LegacyProtocols\Conversation;

use App\Features\Actions\Conversation\Contracts\OutboundMessageSender;
use App\Models\Server\Outbox;

class ActivityPubOutboundMessageSender implements OutboundMessageSender
{
    /**
     * @return array<string, mixed>
     */
    public function send(Outbox $outbox): array
    {
        $target = is_string($outbox->target) ? trim($outbox->target) : '';

        if ($target === '') {
            throw new \RuntimeException('ActivityPub target is required.');
        }

        return [
            'ok' => true,
            'protocol' => 'activitypub',
            'provider' => $outbox->provider,
            'target' => $target,
            'delivery' => 'queued_for_adapter',
            'provider_message_id' => data_get($outbox->payload ?? [], 'message.id'),
        ];
    }
}
