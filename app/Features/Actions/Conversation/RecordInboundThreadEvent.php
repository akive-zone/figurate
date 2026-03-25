<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\ThreadEvent;

class RecordInboundThreadEvent
{
    public function execute(
        Thread $thread,
        Post $message,
        string $protocol,
        ?string $provider,
        string $externalActorId,
        string $idempotencyKey,
        ?string $externalMessageId,
    ): void {
        $thread->events()->create([
            'thread_actor_id' => null,
            'post_id' => $message->id,
            'event_key' => "protocol.{$protocol}.inbound",
            'layer' => ThreadEvent::LayerExecution,
            'kind' => ThreadEvent::KindOrchestration,
            'operation' => 'protocol.inbound_message',
            'state' => ThreadEvent::StateReceived,
            'event_type' => "{$protocol}.inbound.received",
            'severity' => 'low',
            'payload' => [
                'protocol' => $protocol,
                'provider' => $provider,
                'external_actor_id' => $externalActorId,
                'outbox_idempotency_key' => $idempotencyKey,
                'external_message_id' => $externalMessageId,
            ],
        ]);
    }
}
