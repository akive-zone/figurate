<?php

namespace App\Features\Actions\Conversation;

class ResolveConversationIdempotencyKey
{
    public function execute(mixed $rawValue): ?string
    {
        if (! is_string($rawValue)) {
            return null;
        }

        $normalized = trim($rawValue);

        return $normalized === '' ? null : $normalized;
    }
}
