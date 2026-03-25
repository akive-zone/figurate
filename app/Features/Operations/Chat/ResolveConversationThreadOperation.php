<?php

namespace App\Features\Operations\Chat;

use App\Features\Actions\Conversation\ApplyConversationPurposeTriggers;
use App\Features\Actions\Conversation\PersistActiveConversationThread;
use App\Features\Actions\Conversation\RecordConversationOperationEvents;
use App\Features\Actions\Conversation\ResolveActiveThreadPresenters;
use App\Features\Actions\Conversation\ResolveBaseConversationThread;
use App\Models\Server\Space;
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
        Space $space,
        User $actor,
        ?int $thread = null,
        ?string $message = null,
    ): OrchestrationDecision {
        return DB::transaction(function () use ($space, $actor, $thread, $message): OrchestrationDecision {
            $actions = [];
            $resolvedThread = $this->resolveBaseConversationThread->execute($space, $actor, $thread);

            if ($thread === null) {
                [$resolvedThread, $triggerActions] = $this->applyConversationPurposeTriggers->execute($space, $resolvedThread, $message);
                $actions = array_merge($actions, $triggerActions);
            }

            $this->persistActiveConversationThread->execute($space, $actor, $resolvedThread);
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
