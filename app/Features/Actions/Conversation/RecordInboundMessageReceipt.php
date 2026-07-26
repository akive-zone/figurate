<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Outbox;
use App\Models\Server\Post;
use App\Models\Server\Thread;

class RecordInboundMessageReceipt
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        Thread $thread,
        Post $message,
        string $idempotencyKey,
        string $protocol,
        ?string $provider,
        string $externalActorId,
        array $payload,
    ): void {
        Outbox::query()->updateOrCreate(
            [
                'direction' => Outbox::DirectionInbound,
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'thread_id' => $thread->id,
                'post_id' => $message->id,
                'protocol' => $protocol,
                'provider' => $provider,
                'target' => $externalActorId,
                'status' => Outbox::StatusReceived,
                'attempts' => 1,
                'available_at' => now(),
                'processed_at' => now(),
                'payload' => $payload,
                'result' => [
                    'post_id' => $message->id,
                    'thread_id' => $thread->id,
                    'ingested_at' => now()->toIso8601String(),
                ],
                'error_message' => null,
            ],
        );
    }
}
