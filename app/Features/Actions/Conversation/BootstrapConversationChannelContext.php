<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BootstrapConversationChannelContext
{
    public function execute(User $actor): Channel
    {
        Gate::authorize('create', Channel::class);

        return DB::transaction(function () use ($actor): Channel {
            $channel = Channel::query()->create([
                'status' => 'open',
            ]);

            $mainThread = $channel->threads()->create([
                'purpose' => Thread::PurposeMain,
                'title' => 'Project Main',
                'phase' => 'request_intake',
                'status' => 'open',
            ]);

            $mainThread->actors()->create([
                'actorable_type' => ThreadActor::ActorRequestAgent,
                'actorable_id' => null,
                'role' => ThreadActor::RolePresenter,
                'status' => ThreadActor::StatusActive,
                'priority' => 1,
                'config' => null,
            ]);

            ChannelActorState::query()->updateOrCreate(
                [
                    'channel_id' => $channel->id,
                    'actorable_type' => $actor->getMorphClass(),
                    'actorable_id' => $actor->getKey(),
                ],
                [
                    'thread_id' => $mainThread->id,
                    'status' => ChannelActorState::StatusActive,
                ],
            );

            return $channel;
        });
    }
}
