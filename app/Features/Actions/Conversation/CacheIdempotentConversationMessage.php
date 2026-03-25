<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Support\Facades\Cache;

class CacheIdempotentConversationMessage
{
    public function execute(Thread $thread, User $actor, ?string $idempotencyKey, Post $message): void
    {
        if ($idempotencyKey === null) {
            return;
        }

        Cache::put($this->cacheKey($thread, $actor, $idempotencyKey), $message->id, now()->addDay());
    }

    protected function cacheKey(Thread $thread, User $actor, string $idempotencyKey): string
    {
        return sprintf('conversation-message-idempotency:%d:%d:%s', $thread->id, $actor->id, $idempotencyKey);
    }
}
