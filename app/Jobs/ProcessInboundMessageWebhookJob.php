<?php

namespace App\Jobs;

use App\Features\Actions\Chat\InboundMessageEnvelope;
use App\Features\Actions\Chat\InboundMessageReceiverResolver;
use App\Features\Actions\Chat\ProtocolRegistry;
use App\Models\Server\Thread;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class ProcessInboundMessageWebhookJob extends ProcessWebhookJob
{
    public function handle(
        InboundMessageReceiverResolver $inboundMessageReceiverResolver,
        ProtocolRegistry $protocolRegistry,
    ): void {
        $payload = is_array($this->webhookCall->payload ?? null) ? $this->webhookCall->payload : [];

        $thread = $this->resolveThread($payload);
        $network = $this->resolveNetwork($protocolRegistry, $payload);
        $provider = $this->resolveProvider($payload);
        $externalActorId = $this->resolveExternalActorId($payload);

        if (! $thread || $network === null || $externalActorId === null) {
            return;
        }

        $receiver = $inboundMessageReceiverResolver->forProtocolTransport($network, 'webhook');

        if (! $receiver) {
            return;
        }

        $receiver->receive(new InboundMessageEnvelope(
            thread: $thread,
            protocol: $network,
            provider: $provider,
            externalActorId: $externalActorId,
            text: $this->resolveText($payload),
            payload: $payload,
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveThread(array $payload): ?Thread
    {
        $threadUuid = $this->trimmedString(
            data_get($payload, 'thread')
            ?? data_get($payload, 'thread_id')
            ?? data_get($payload, 'context.thread')
            ?? data_get($payload, 'context.thread_id')
            ?? data_get($payload, 'meta.thread')
            ?? data_get($payload, 'meta.thread_id')
        );

        if ($threadUuid === null) {
            return null;
        }

        return Thread::query()
            ->where('uuid', $threadUuid)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveNetwork(ProtocolRegistry $protocolRegistry, array $payload): ?string
    {
        $protocolFromWebhookConfig = $protocolRegistry->protocolForWebhookConfig((string) $this->webhookCall->name);

        if ($protocolFromWebhookConfig !== null) {
            return $protocolFromWebhookConfig;
        }

        return $this->trimmedString(
            data_get($payload, 'network')
            ?? data_get($payload, 'protocol')
            ?? data_get($payload, 'context.network')
            ?? data_get($payload, 'context.protocol')
            ?? data_get($payload, 'meta.network')
            ?? data_get($payload, 'meta.protocol')
            ?? $this->headerValue('x-chat-network')
            ?? $this->headerValue('x-network')
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveExternalActorId(array $payload): ?string
    {
        return $this->trimmedString(
            data_get($payload, 'external_actor_id')
            ?? data_get($payload, 'actor.id')
            ?? data_get($payload, 'context.external_actor_id')
            ?? data_get($payload, 'meta.external_actor_id')
            ?? $this->headerValue('x-external-actor-id')
            ?? $this->headerValue('x-actor-id')
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveProvider(array $payload): ?string
    {
        return $this->trimmedString(
            data_get($payload, 'provider')
            ?? data_get($payload, 'source.provider')
            ?? data_get($payload, 'context.provider')
            ?? data_get($payload, 'meta.provider')
            ?? $this->headerValue('x-chat-provider')
            ?? $this->headerValue('x-provider')
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveText(array $payload): ?string
    {
        return $this->trimmedString(
            data_get($payload, 'text')
            ?? data_get($payload, 'message.text')
            ?? data_get($payload, 'body.text')
            ?? data_get($payload, 'content.text')
        );
    }

    protected function headerValue(string $header): ?string
    {
        $headers = is_array($this->webhookCall->headers ?? null) ? $this->webhookCall->headers : [];

        foreach ($headers as $key => $value) {
            if (! is_string($key) || strtolower(trim($key)) !== strtolower($header)) {
                continue;
            }

            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            return $this->trimmedString($value);
        }

        return null;
    }

    protected function trimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
