<?php

namespace App\Features\Operations\Chat;

use App\Features\Actions\Conversation\ApplyConversationPurposeTriggers;
use App\Features\Actions\Conversation\PersistActiveConversationThread;
use App\Features\Actions\Conversation\RecordConversationOperationEvents;
use App\Features\Actions\Conversation\ResolveActiveThreadPresenters;
use App\Features\Actions\Conversation\ResolveBaseConversationThread;
use App\Models\Server\Channel;
use App\Models\Server\User;
use App\Support\Orchestrate\OrchestrationDecision;
use Illuminate\Support\Facades\DB;

class ResolveConversationThreadOperation
{
    public function __construct(
        protected ResolveBaseConversationThread $resolveBaseConversationThread,
        protected ApplyConversationPurposeTriggers $applyConversationPurposeTriggers,
        protected PersistActiveConversationThread $persistActiveConversationThread,
        protected RecordConversationOperationEvents $recordConversationOperationEvents,
        protected ResolveActiveThreadPresenters $resolveActiveThreadPresenters,
    ) {}

    public function run(
        Channel $channel,
        User $actor,
        ?int $thread = null,
        ?string $message = null,
    ): OrchestrationDecision {
        return DB::transaction(function () use ($channel, $actor, $thread, $message): OrchestrationDecision {
            $actions = [];
            $resolvedThread = $this->resolveBaseConversationThread->execute($channel, $actor, $thread);

            if ($thread === null) {
                [$resolvedThread, $triggerActions] = $this->applyConversationPurposeTriggers->execute($channel, $resolvedThread, $message);
                $actions = array_merge($actions, $triggerActions);
            }

            $this->persistActiveConversationThread->execute($channel, $actor, $resolvedThread);
            $this->recordConversationOperationEvents->execute($resolvedThread, $actions);

            $primaryPresenter = $this->resolveActiveThreadPresenters->execute($resolvedThread)->first();
            $hasPresenter = $primaryPresenter !== null;

            return new OrchestrationDecision(
                thread: $resolvedThread,
                responderType: $hasPresenter ? 'presenter' : 'direct',
                responderKey: $primaryPresenter?->actorName(),
                actions: $actions,
            );
        });
    }
}
