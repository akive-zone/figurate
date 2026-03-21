<?php

namespace App\Features\Actions\Chat;

use App\Ai\Support\ChatAgentExecutor;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Illuminate\Support\Collection;

class QueuePresenterReplies
{
    public function __construct(protected ChatAgentExecutor $chatAgentExecutor) {}

    /**
     * @param  Collection<int, ThreadActor>  $presenters
     */
    public function execute(
        Thread $thread,
        Message $userMessage,
        User $actor,
        Collection $presenters,
        string $broadcastChannelId,
    ): void {
        $presenters->each(function (ThreadActor $presenter) use ($thread, $userMessage, $actor, $broadcastChannelId): void {
            $this->chatAgentExecutor->queue(
                thread: $thread,
                userMessage: $userMessage,
                user: $actor,
                threadActor: $presenter,
                broadcastChannelId: $broadcastChannelId,
            );
        });
    }
}
