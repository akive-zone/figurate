<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Space;
use App\Models\Server\SpaceRelation;
use App\Models\Server\Thread;
use App\Models\Server\ThreadRelation;
use Illuminate\Database\Eloquent\Model;

class ResolveConversationThreadContext
{
    public function execute(string $threadUuid, mixed $spaceUuid = null): array
    {
        $thread = Thread::query()
            ->where('uuid', $threadUuid)
            ->firstOrFail();

        return [$this->resolveThreadSpace($thread, $spaceUuid), $thread->id];
    }

    protected function resolveThreadSpace(Thread $thread, mixed $spaceUuid): Space
    {
        if (is_string($spaceUuid) && $spaceUuid !== '') {
            $space = Space::query()
                ->where('uuid', $spaceUuid)
                ->firstOrFail();

            if (! $space->conversationThreadIds()->contains($thread->getKey())) {
                abort(404, 'The selected thread does not belong to this space.');
            }

            return $space;
        }

        $threadable = $thread->threadable;

        if ($threadable instanceof Space) {
            return $threadable;
        }

        $relatedSpace = $thread->relatedOne(Space::class);

        if ($relatedSpace instanceof Space) {
            return $relatedSpace;
        }

        $threadMorphClass = $thread->getMorphClass();
        $relatedSpaceId = SpaceRelation::query()
            ->where('relationable_type', $threadMorphClass)
            ->where('relationable_id', $thread->getKey())
            ->value('space_id');

        if (is_int($relatedSpaceId) && $relatedSpaceId > 0) {
            return Space::query()->findOrFail($relatedSpaceId);
        }

        $spaceMorphClass = (new Space)->getMorphClass();
        $threadRelationSpaceId = ThreadRelation::query()
            ->where('thread_id', $thread->getKey())
            ->where('relationable_type', $spaceMorphClass)
            ->value('relationable_id');

        if (is_int($threadRelationSpaceId) && $threadRelationSpaceId > 0) {
            return Space::query()->findOrFail($threadRelationSpaceId);
        }

        if ($threadable instanceof Model) {
            $threadableSpaceId = SpaceRelation::query()
                ->where('relationable_type', $threadable->getMorphClass())
                ->where('relationable_id', $threadable->getKey())
                ->value('space_id');

            if (is_int($threadableSpaceId) && $threadableSpaceId > 0) {
                return Space::query()->findOrFail($threadableSpaceId);
            }
        }

        abort(422, 'A space id is required for this thread context.');
    }
}
