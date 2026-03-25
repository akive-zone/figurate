<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Support\Facades\Cache;

class FindExistingIdempotentConversationMessage
{
    public function execute(Thread $thread, User $actor, ?string $idempotencyKey): ?Post
    {
        if ($idempotencyKey === null) {
            return null;
        }

        $cachedMessageId = Cache::get($this->cacheKey($thread, $actor, $idempotencyKey));
        if (! is_int($cachedMessageId)) {
            return null;
        }

        return Post::query()
            ->messageType()
            ->whereKey($cachedMessageId)
            ->forThread($thread)
            ->fromSender($actor)
            ->first();
    }

    protected function cacheKey(Thread $thread, User $actor, string $idempotencyKey): string
    {
        return sprintf('conversation-message-idempotency:%d:%d:%s', $thread->id, $actor->id, $idempotencyKey);
    }
}
