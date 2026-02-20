<?php

namespace App\Actions\Server\Chat;

use App\Jobs\GenerateAgentReply;
use App\Models\Server\Channel;
use App\Models\Server\Message;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Illuminate\Support\Collection;

class PromptPresenterThread
{
    /**
     * @param  Collection<int, ThreadActor>  $activePresenters
     */
    public function __invoke(
        Channel $channel,
        ?ServiceRequest $serviceRequest,
        Thread $thread,
        User $actor,
        string $content,
        Collection $activePresenters
    ): Message {
        if (! $this->canActorWrite($channel, $serviceRequest, $actor)) {
            abort(403);
        }

        if ($content === '') {
            abort(422, 'A text message is required for agent prompts.');
        }

        $userMessage = $thread->messages()->create([
            'senderable_type' => $actor->getMorphClass(),
            'senderable_id' => $actor->getKey(),
            'type' => 'text',
            'body' => $content,
            'attachments' => null,
            'meta' => [
                'source' => 'agent_prompt',
            ],
        ]);

        $activePresenters->each(function (ThreadActor $presenter) use ($thread, $userMessage, $actor): void {
            GenerateAgentReply::dispatch(
                threadId: $thread->id,
                userMessageId: $userMessage->id,
                actorId: $actor->id,
                primaryPresenterActorId: $presenter->id,
            )->afterCommit();
        });

        return $userMessage;
    }

    protected function canActorWrite(Channel $channel, ?ServiceRequest $serviceRequest, User $actor): bool
    {
        if ($serviceRequest) {
            return $serviceRequest->hasParticipant($actor);
        }

        return $channel->hasActor($actor);
    }
}
