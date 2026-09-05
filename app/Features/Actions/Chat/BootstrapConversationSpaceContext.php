<?php

namespace App\Features\Actions\Chat;

use App\Events\Server\Chat\ConversationThreadCreated;
use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BootstrapConversationSpaceContext
{
    public function execute(User $actor): Space
    {
        Gate::authorize('create', Space::class);

        return DB::transaction(function () use ($actor): Space {
            $space = Space::query()->create([
                'status' => 'open',
            ]);

            $mainThread = $space->threads()->create([
                'purpose' => Thread::PurposeMain,
                'title' => 'Main',
                'phase' => Thread::PhaseInitial,
                'status' => 'open',
            ]);

            ConversationThreadCreated::dispatch($mainThread);

            SpaceActorState::query()->updateOrCreate(
                [
                    'space_id' => $space->id,
                    'actorable_type' => $actor->getMorphClass(),
                    'actorable_id' => $actor->getKey(),
                ],
                [
                    'thread_id' => $mainThread->id,
                    'status' => SpaceActorState::StatusActive,
                ],
            );

            return $space;
        });
    }
}
