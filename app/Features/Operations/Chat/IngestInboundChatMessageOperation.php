<?php

namespace App\Features\Operations\Chat;

use App\Features\Actions\Chat\DispatchThreadMessage;
use App\Features\Actions\Chat\FindExistingInboundMessage;
use App\Features\Actions\Chat\InboundMessageEnvelope;
use App\Features\Actions\Chat\RecordInboundMessageReceipt;
use App\Features\Actions\Chat\RecordInboundThreadEvent;
use App\Features\Actions\Chat\ResolveInboundMessageIdempotency;
use App\Features\Actions\Chat\ThreadMessageEntry;
use App\Models\Server\Post;

class IngestInboundChatMessageOperation
{
    public function __construct(
        protected DispatchThreadMessage $dispatchThreadMessage,
        protected ResolveInboundMessageIdempotency $resolveInboundMessageIdempotency,
        protected FindExistingInboundMessage $findExistingInboundMessage,
        protected RecordInboundMessageReceipt $recordInboundMessageReceipt,
        protected RecordInboundThreadEvent $recordInboundThreadEvent,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function run(InboundMessageEnvelope $envelope): Post
    {
        $thread = $envelope->thread;
        $payload = $envelope->payload;
        $inboundMessageContext = $this->resolveInboundMessageIdempotency->execute(
            protocol: $envelope->protocol,
            provider: $envelope->provider,
            thread: $thread,
            externalActorId: $envelope->externalActorId,
            payload: $payload,
        );
        $existingMessage = $this->findExistingInboundMessage->execute($inboundMessageContext['idempotency_key']);

        if ($existingMessage instanceof Post) {
            return $existingMessage;
        }

        $message = $this->dispatchThreadMessage->execute(ThreadMessageEntry::systemMessage(
            thread: $thread,
            text: $envelope->text,
            meta: [
                'protocol' => $inboundMessageContext['protocol'],
                'provider' => $inboundMessageContext['provider'],
                'external_actor_id' => $inboundMessageContext['external_actor_id'],
                'external_payload' => $payload,
            ],
            source: "{$inboundMessageContext['protocol']}_inbound",
        ));

        $this->recordInboundMessageReceipt->execute(
            thread: $thread,
            message: $message,
            idempotencyKey: $inboundMessageContext['idempotency_key'],
            protocol: $inboundMessageContext['protocol'],
            provider: $inboundMessageContext['provider'],
            externalActorId: $inboundMessageContext['external_actor_id'],
            payload: $payload,
        );
        $this->recordInboundThreadEvent->execute(
            thread: $thread,
            message: $message,
            protocol: $inboundMessageContext['protocol'],
            provider: $inboundMessageContext['provider'],
            externalActorId: $inboundMessageContext['external_actor_id'],
            idempotencyKey: $inboundMessageContext['idempotency_key'],
            externalMessageId: $inboundMessageContext['external_message_id'],
        );

        return $message;
    }
}
