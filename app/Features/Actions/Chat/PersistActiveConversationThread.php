<?php

namespace App\Features\Actions\Chat;

use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\Thread;
use App\Models\Server\User;

class PersistActiveConversationThread
{
    public function execute(Space $space, User $actor, Thread $thread): void
    {
        SpaceActorState::query()->updateOrCreate(
            [
                'space_id' => $space->id,
                'actorable_type' => $actor->getMorphClass(),
                'actorable_id' => $actor->getKey(),
            ],
            [
                'thread_id' => $thread->id,
                'status' => SpaceActorState::StatusActive,
            ],
        );
    }
}
