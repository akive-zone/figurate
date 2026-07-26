<?php

namespace App\Features\Actions\Chat;

use App\Models\Server\Thread;
use Illuminate\Support\Arr;

class ResolveInboundMessageIdempotency
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{idempotency_key: string, protocol: string, provider: string|null, external_actor_id: string, external_message_id: string|null}
     */
    public function execute(
        string $protocol,
        ?string $provider,
        Thread $thread,
        string $externalActorId,
        array $payload,
    ): array {
        $normalizedProtocol = strtolower(trim($protocol));
        $normalizedProvider = $this->normalizedProvider($provider);
        $normalizedActorId = trim($externalActorId);
        $externalMessageId = Arr::get($payload, 'message.id') ?? Arr::get($payload, 'id');
        $normalizedExternalMessageId = is_string($externalMessageId) ? trim($externalMessageId) : null;

        return [
            'idempotency_key' => $this->idempotencyKey($normalizedProtocol, $normalizedProvider, $thread, $normalizedActorId, $payload),
            'protocol' => $normalizedProtocol,
            'provider' => $normalizedProvider,
            'external_actor_id' => $normalizedActorId,
            'external_message_id' => $normalizedExternalMessageId,
        ];
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
        $normalizedExternalMessageId = is_string($externalMessageId) ? trim($externalMessageId) : '';

        if ($normalizedExternalMessageId !== '') {
            return sprintf(
                'inbound:%s:%s:%d:%s:%s',
                $protocol,
                $provider ?? 'default',
                $thread->id,
                $externalActorId,
                $normalizedExternalMessageId
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
