<?php

namespace App\Features\Actions\Chat;

use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Support\Facades\Cache;

class CacheIdempotentChatMessage
{
    public function execute(Thread $thread, User $actor, ?string $idempotencyKey, Message $message): void
    {
        if ($idempotencyKey === null) {
            return;
        }

        Cache::put($this->cacheKey($thread, $actor, $idempotencyKey), $message->id, now()->addDay());
    }

    protected function cacheKey(Thread $thread, User $actor, string $idempotencyKey): string
    {
        return sprintf('chat-message-idempotency:%d:%d:%s', $thread->id, $actor->id, $idempotencyKey);
    }
}
