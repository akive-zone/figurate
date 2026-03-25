<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Thread;
use App\Models\Server\User;

class PersistActiveConversationThread
{
    public function execute(Channel $channel, User $actor, Thread $thread): void
    {
        ChannelActorState::query()->updateOrCreate(
            [
                'channel_id' => $channel->id,
                'actorable_type' => $actor->getMorphClass(),
                'actorable_id' => $actor->getKey(),
            ],
            [
                'thread_id' => $thread->id,
                'status' => ChannelActorState::StatusActive,
            ],
        );
    }
}
