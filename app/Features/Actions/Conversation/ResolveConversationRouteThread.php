<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Support\Facades\Gate;

class ResolveConversationRouteThread
{
    /**
     * @return array{0: ?Thread, 1: ?Space}
     */
    public function execute(string $conversation, User $actor): array
    {
        $threadRecord = Thread::query()
            ->where('uuid', $conversation)
            ->first();

        if ($threadRecord instanceof Thread) {
            Gate::forUser($actor)->authorize('view', $threadRecord);

            $spaceRecord = null;
            if ($threadRecord->threadable instanceof Space) {
                $spaceRecord = $threadRecord->threadable;
                Gate::forUser($actor)->authorize('view', $spaceRecord);
            }

            return [$threadRecord, $spaceRecord];
        }

        $spaceRecord = Space::query()
            ->where('uuid', $conversation)
            ->firstOrFail();

        Gate::forUser($actor)->authorize('view', $spaceRecord);

        $threadIds = $spaceRecord->conversationThreadIds();

        if ($threadIds->isEmpty()) {
            return [null, $spaceRecord];
        }

        $actorStateThreadId = $spaceRecord->actorStates()
            ->whereMorphedTo('actor', $actor)
            ->where('status', SpaceActorState::StatusActive)
            ->value('thread_id');

        if (is_int($actorStateThreadId) && $actorStateThreadId > 0 && $threadIds->contains($actorStateThreadId)) {
            $activeThread = Thread::query()
                ->whereKey($actorStateThreadId)
                ->first();

            if ($activeThread instanceof Thread) {
                Gate::forUser($actor)->authorize('view', $activeThread);

                return [$activeThread, $spaceRecord];
            }
        }

        $latestThread = Thread::query()
            ->whereIn('id', $threadIds->all())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($latestThread instanceof Thread) {
            Gate::forUser($actor)->authorize('view', $latestThread);
        }

        return [$latestThread, $spaceRecord];
    }
}
