<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Support\Facades\Gate;

class ResolveConversationRouteThread
{
    /**
     * @return array{0: ?Thread, 1: ?Channel}
     */
    public function execute(string $conversation, User $actor): array
    {
        $threadRecord = Thread::query()
            ->where('uuid', $conversation)
            ->first();

        if ($threadRecord instanceof Thread) {
            Gate::forUser($actor)->authorize('view', $threadRecord);

            $channelRecord = null;
            if ($threadRecord->threadable instanceof Channel) {
                $channelRecord = $threadRecord->threadable;
                Gate::forUser($actor)->authorize('view', $channelRecord);
            }

            return [$threadRecord, $channelRecord];
        }

        $channelRecord = Channel::query()
            ->where('uuid', $conversation)
            ->firstOrFail();

        Gate::forUser($actor)->authorize('view', $channelRecord);

        $threadIds = $channelRecord->conversationThreadIds();

        if ($threadIds->isEmpty()) {
            return [null, $channelRecord];
        }

        $actorStateThreadId = $channelRecord->actorStates()
            ->where('actorable_type', $actor->getMorphClass())
            ->where('actorable_id', $actor->id)
            ->where('status', ChannelActorState::StatusActive)
            ->value('thread_id');

        if (is_int($actorStateThreadId) && $actorStateThreadId > 0 && $threadIds->contains($actorStateThreadId)) {
            $activeThread = Thread::query()
                ->whereKey($actorStateThreadId)
                ->first();

            if ($activeThread instanceof Thread) {
                Gate::forUser($actor)->authorize('view', $activeThread);

                return [$activeThread, $channelRecord];
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

        return [$latestThread, $channelRecord];
    }
}
