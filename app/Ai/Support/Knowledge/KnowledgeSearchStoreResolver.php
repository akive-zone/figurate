<?php

namespace App\Ai\Support\Knowledge;

use App\Ai\Support\ThreadContextResolver;
use App\Models\Server\Channel;
use App\Models\Server\Thread;

class KnowledgeSearchStoreResolver
{
    public function __construct(
        protected ThreadContextResolver $threadContextResolver = new ThreadContextResolver,
    ) {}

    /**
     * @return list<string>
     */
    public function resolveExternalStoreIds(Thread $thread): array
    {
        $threadStoreIds = $thread->stores()
            ->whereNotNull('external_store_id')
            ->where('status', 'active')
            ->pluck('external_store_id')
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->values()
            ->all();

        $channel = $this->threadContextResolver->resolveChannel($thread);
        $channelStoreIds = $channel instanceof Channel
            ? $channel->stores()
                ->whereNotNull('external_store_id')
                ->where('status', 'active')
                ->pluck('external_store_id')
                ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
                ->values()
                ->all()
            : [];

        return collect()
            ->merge($threadStoreIds)
            ->merge($channelStoreIds)
            ->unique()
            ->values()
            ->all();
    }
}
