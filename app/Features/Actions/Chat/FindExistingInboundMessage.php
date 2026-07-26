<?php

namespace App\Features\Actions\Chat;

use App\Models\Server\Outbox;
use App\Models\Server\Post;

class FindExistingInboundMessage
{
    public function execute(string $idempotencyKey): ?Post
    {
        $existingInbound = Outbox::query()
            ->where('direction', Outbox::DirectionInbound)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (! $existingInbound?->post_id) {
            return null;
        }

        return Post::query()->find($existingInbound->post_id);
    }
}
