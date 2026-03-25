<?php

namespace App\Features\Actions\Conversation;

use App\Ai\Support\AgentExecutor;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Illuminate\Support\Collection;

class QueuePresenterReplies
{
    public function __construct(protected AgentExecutor $agentExecutor) {}

    /**
     * @param  Collection<int, ThreadActor>  $presenters
     */
    public function execute(
        Thread $thread,
        Message $userMessage,
        User $actor,
        Collection $presenters,
        string $broadcastSpaceId,
    ): void {
        $presenters->each(function (ThreadActor $presenter) use ($thread, $userMessage, $actor, $broadcastSpaceId): void {
            $this->agentExecutor->queue(
                thread: $thread,
                userMessage: $userMessage,
                user: $actor,
                threadActor: $presenter,
                broadcastSpaceId: $broadcastSpaceId,
            );
        });
    }
}
