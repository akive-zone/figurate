<?php

namespace App\Features\Actions\Chat;

use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Builder;

class ResolveBaseConversationThread
{
    public function execute(Channel $channel, User $actor, ?int $thread = null): Thread
    {
        $channelThreadIds = $channel->conversationThreadIds();

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

        $actorState = ChannelActorState::query()
            ->where('channel_id', $channel->id)
            ->where('actorable_type', $actor->getMorphClass())
            ->where('actorable_id', $actor->getKey())
            ->first();

        if ($actorState?->thread_id) {
            $thread = $this->threadsQuery($channel)
                ->where('status', 'open')
                ->whereKey($actorState->thread_id)
                ->first();

            if ($thread) {
                return $thread;
            }
        }

        $thread = $this->threadsQuery($channel)
            ->where('status', 'open')
            ->where('purpose', Thread::PurposeMain)
            ->orderBy('id')
            ->first();

        if ($thread) {
            return $thread;
        }

        $thread = $this->threadsQuery($channel)
            ->where('status', 'open')
            ->latest('id')
            ->first();

        if ($thread) {
            return $thread;
        }

        return $this->createMainThread($channel);
    }

    protected function threadsQuery(Channel $channel): Builder
    {
        return Thread::query()->whereIn('id', $channel->conversationThreadIds()->all());
    }

    protected function createMainThread(Channel $channel): Thread
    {
        $thread = $channel->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Project Main',
            'phase' => 'request_intake',
            'status' => 'open',
        ]);

        $thread->actors()->create([
            'actorable_type' => ThreadActor::ActorRequestAgent,
            'actorable_id' => null,
            'role' => ThreadActor::RolePresenter,
            'status' => ThreadActor::StatusActive,
            'priority' => 1,
            'config' => null,
        ]);

        return $thread;
    }
}
