<?php

namespace App\Ai\Support;

use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\ThreadRelation;

class ThreadContextResolver
{
    public function resolveSpace(Thread $thread): ?Space
    {
        $threadable = $thread->threadable;

        if ($threadable instanceof Space) {
            return $threadable;
        }

        if ($threadable instanceof Post) {
            $postable = $threadable->postable;

            if ($postable instanceof Space) {
                return $postable;
            }

            $relatedSpace = $threadable->relatedOne(Space::class);

            if ($relatedSpace instanceof Space) {
                return $relatedSpace;
            }
        }

        $spaceRelation = ThreadRelation::query()
            ->where('thread_id', $thread->id)
            ->where('relationable_type', (new Space)->getMorphClass())
            ->latest('id')
            ->first();

        if (! $spaceRelation) {
            return null;
        }

        return Space::query()->find($spaceRelation->relationable_id);
    }
}
