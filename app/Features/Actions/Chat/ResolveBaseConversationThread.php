<?php

namespace App\Features\Actions\Chat;

use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Builder;

class ResolveBaseConversationThread
{
    public function execute(Space $space, User $actor, ?int $thread = null): Thread
    {
        $channelThreadIds = $space->conversationThreadIds();

        if ($thread !== null) {
            $thread = Thread::query()
                ->whereIn('id', $channelThreadIds->all())
                ->where('status', 'open')
                ->whereKey($thread)
                ->first();

            if (! $thread) {
                abort(404);
            }

            return $thread;
        }

        $actorState = SpaceActorState::query()
            ->where('space_id', $space->id)
            ->whereMorphedTo('actor', $actor)
            ->first();

        if ($actorState?->thread_id) {
            $thread = $this->threadsQuery($space)
                ->where('status', 'open')
                ->whereKey($actorState->thread_id)
                ->first();

            if ($thread) {
                return $thread;
            }
        }

        $thread = $this->threadsQuery($space)
            ->where('status', 'open')
            ->where('purpose', Thread::PurposeMain)
            ->orderBy('id')
            ->first();

        if ($thread) {
            return $thread;
        }

        $thread = $this->threadsQuery($space)
            ->where('status', 'open')
            ->latest('id')
            ->first();

        if ($thread) {
            return $thread;
        }

        return $this->createMainThread($space);
    }

    protected function threadsQuery(Space $space): Builder
    {
        return Thread::query()->whereIn('id', $space->conversationThreadIds()->all());
    }

    protected function createMainThread(Space $space): Thread
    {
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Project Main',
            'phase' => 'request_intake',
            'status' => 'open',
        ]);

        $thread->actors()->create([
            'actorable_type' => ThreadActor::ActorCoordinator,
            'actorable_id' => null,
            'role' => ThreadActor::RolePresenter,
            'status' => ThreadActor::StatusActive,
            'priority' => 1,
            'config' => null,
        ]);

        return $thread;
    }
}
