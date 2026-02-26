<?php

namespace App\Ai\Support;

use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\ThreadRelation;

class ThreadContextResolver
{
    public function resolveChannel(Thread $thread): ?Channel
    {
        $threadable = $thread->threadable;

        if ($threadable instanceof Channel) {
            return $threadable;
        }

        if ($threadable instanceof Post) {
            $postable = $threadable->postable;

            if ($postable instanceof Channel) {
                return $postable;
            }

            $relatedChannel = $threadable->relatedOne(Channel::class);

            if ($relatedChannel instanceof Channel) {
                return $relatedChannel;
            }
        }

        $channelRelation = ThreadRelation::query()
            ->where('thread_id', $thread->id)
            ->where('relationable_type', (new Channel)->getMorphClass())
            ->latest('id')
            ->first();

        if (! $channelRelation) {
            return null;
        }

        return Channel::query()->find($channelRelation->relationable_id);
    }
}
