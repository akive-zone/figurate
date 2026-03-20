<?php

namespace App\Features\Actions\Chat;

use App\Models\Server\Message;
use App\Models\Server\Outbox;
use App\Models\Server\Thread;
use App\Models\Server\ThreadEvent;
use Illuminate\Support\Arr;

class IngestInboundMessage
{
    public function __construct(protected DispatchThreadMessage $dispatchThreadMessage) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(InboundMessageEnvelope $envelope): Message
    {
        $thread = $envelope->thread;
        $payload = $envelope->payload;
        $normalizedProtocol = strtolower(trim($envelope->protocol));
        $normalizedProvider = $this->normalizedProvider($envelope->provider);
        $normalizedActorId = trim($envelope->externalActorId);
        $idempotencyKey = $this->idempotencyKey($normalizedProtocol, $normalizedProvider, $thread, $normalizedActorId, $payload);
        $externalMessageId = Arr::get($payload, 'message.id') ?? Arr::get($payload, 'id');
        $normalizedExternalMessageId = is_string($externalMessageId) ? trim($externalMessageId) : null;

        $existingInbound = Outbox::query()
            ->where('direction', Outbox::DirectionInbound)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingInbound?->message_id) {
            $existingMessage = Message::query()->find($existingInbound->message_id);

            if ($existingMessage) {
                return $existingMessage;
            }
        }

        $message = $this->dispatchThreadMessage->execute(ThreadMessageEntry::agentMessage(
            thread: $thread,
            text: $envelope->text,
            meta: [
                'protocol' => $normalizedProtocol,
                'provider' => $normalizedProvider,
                'external_actor_id' => $normalizedActorId,
                'external_payload' => $payload,
            ],
            source: "{$normalizedProtocol}_inbound",
        ));

        Outbox::query()->updateOrCreate(
            [
                'direction' => Outbox::DirectionInbound,
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'thread_id' => $thread->id,
                'message_id' => $message->id,
                'protocol' => $normalizedProtocol,
                'provider' => $normalizedProvider,
                'target' => $normalizedActorId,
                'status' => Outbox::StatusReceived,
                'attempts' => 1,
                'available_at' => now(),
                'processed_at' => now(),
                'payload' => $payload,
                'result' => [
                    'message_id' => $message->id,
                    'thread_id' => $thread->id,
                    'ingested_at' => now()->toIso8601String(),
                ],
                'error_message' => null,
            ],
        );

        $thread->events()->create([
            'thread_actor_id' => null,
            'message_id' => $message->id,
            'event_key' => "protocol.{$normalizedProtocol}.inbound",
            'layer' => ThreadEvent::LayerExecution,
            'kind' => ThreadEvent::KindOrchestration,
            'operation' => 'protocol.inbound_message',
            'state' => ThreadEvent::StateReceived,
            'event_type' => "{$normalizedProtocol}.inbound.received",
            'severity' => 'low',
            'payload' => [
                'protocol' => $normalizedProtocol,
                'provider' => $normalizedProvider,
                'external_actor_id' => $normalizedActorId,
                'outbox_idempotency_key' => $idempotencyKey,
                'external_message_id' => $normalizedExternalMessageId,
            ],
        ]);

        return $message;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function idempotencyKey(
        string $protocol,
        ?string $provider,
        Thread $thread,
        string $externalActorId,
        array $payload
    ): string {
        $externalMessageId = Arr::get($payload, 'message.id') ?? Arr::get($payload, 'id');
        $normalizedMessageId = is_string($externalMessageId) ? trim($externalMessageId) : '';

        if ($normalizedMessageId !== '') {
            return sprintf(
                'inbound:%s:%s:%d:%s:%s',
                $protocol,
                $provider ?? 'default',
                $thread->id,
                $externalActorId,
                $normalizedMessageId
            );
        }

        return sprintf(
            'inbound:%s:%s:%d:%s:%s',
            $protocol,
            $provider ?? 'default',
            $thread->id,
            $externalActorId,
            sha1((string) json_encode($payload))
        );
    }

    protected function normalizedProvider(?string $provider): ?string
    {
        if (! is_string($provider)) {
            return null;
        }

        $normalized = trim($provider);

        return $normalized === '' ? null : $normalized;
    }
}
