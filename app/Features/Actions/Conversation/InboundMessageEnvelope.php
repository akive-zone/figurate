<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Thread;

readonly class InboundMessageEnvelope
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public Thread $thread,
        public string $protocol,
        public ?string $provider,
        public string $externalActorId,
        public ?string $text,
        public array $payload = [],
    ) {}
}
