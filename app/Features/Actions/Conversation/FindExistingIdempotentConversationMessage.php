<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Support\Facades\Cache;

class FindExistingIdempotentConversationMessage
{
    public function execute(Thread $thread, User $actor, ?string $idempotencyKey): ?Message
    {
        if ($idempotencyKey === null) {
            return null;
        }

        $cachedMessageId = Cache::get($this->cacheKey($thread, $actor, $idempotencyKey));
        if (! is_int($cachedMessageId)) {
            return null;
        }

        return Message::query()
            ->whereKey($cachedMessageId)
            ->where('messageable_type', $thread->getMorphClass())
            ->where('messageable_id', $thread->getKey())
            ->where('senderable_type', $actor->getMorphClass())
            ->where('senderable_id', $actor->getKey())
            ->first();
    }

    protected function cacheKey(Thread $thread, User $actor, string $idempotencyKey): string
    {
        return sprintf('conversation-message-idempotency:%d:%d:%s', $thread->id, $actor->id, $idempotencyKey);
    }
}
