<?php

namespace App\Ai\Support;

use App\Ai\Support\Knowledge\KnowledgeSearchStoreResolver;
use App\Ai\Tools\AcknowledgeAssessmentTool;
use App\Ai\Tools\AutoModeSelectorTool;
use App\Ai\Tools\ContextBudgetTool;
use App\Ai\Tools\ConversationAuditTool;
use App\Ai\Tools\CreateOrderFromQuoteTool;
use App\Ai\Tools\CreateRequestFromConversationTool;
use App\Ai\Tools\DualWriteDiffTool;
use App\Ai\Tools\GetChannelFulfillmentFlowTool;
use App\Ai\Tools\ModePolicyTool;
use App\Ai\Tools\PrivacyGuardTool;
use App\Ai\Tools\ReplayTool;
use App\Ai\Tools\SessionForkTool;
use App\Ai\Tools\SessionHealthTool;
use App\Ai\Tools\SessionMergeSummaryTool;
use App\Ai\Tools\SessionResetTool;
use App\Ai\Tools\SessionTransferTool;
use App\Ai\Tools\SuggestProfilesForRequestTool;
use App\Ai\Tools\UpsertAssessmentTool;
use App\Ai\Tools\WriteMemoryFileTool;
use App\Models\Server\Channel;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Providers\Tools\FileSearch;

class ChatToolResolver
{
    public function __construct(
        protected KnowledgeSearchStoreResolver $knowledgeSearchStoreResolver = new KnowledgeSearchStoreResolver,
    ) {}

    /**
     * @return list<Tool>
     */
    public function resolve(Thread $thread, User $user): array
    {
        $primaryActor = $thread->primaryPresenterActor()?->actorName();
        $sharedTools = $this->sharedTools($thread, $user);

        if ($primaryActor === ThreadActor::ActorRequestAgent && $this->canCreateRequestFromThread($thread, $user)) {
            $threadable = $thread->threadable;

            if ($threadable instanceof Channel) {
                return [
                    ...$sharedTools,
                    new CreateRequestFromConversationTool($thread, $threadable, $user),
                ];
            }
        }

        $serviceRequest = $thread->threadable;
        if (! $serviceRequest instanceof ServiceRequest) {
            return [];
        }

        $isAsker = $this->isAsker($serviceRequest, $user);
        $isWorker = $this->isWorker($serviceRequest, $user);

        if ($primaryActor === ThreadActor::ActorRequestAgent) {
            return $isAsker
                ? [
                    ...$sharedTools,
                    new CreateOrderFromQuoteTool($thread, $serviceRequest, $user),
                ]
                : $sharedTools;
        }

        if ($primaryActor === ThreadActor::ActorOrderAgent) {
            $tools = [...$sharedTools];

            if ($isAsker) {
                $tools[] = new AcknowledgeAssessmentTool($thread, $serviceRequest, $user);
            }

            if ($isWorker) {
                $tools[] = new UpsertAssessmentTool($thread, $serviceRequest, $user);
            }

            return $tools;
        }

        return $sharedTools;
    }

    /**
     * @return list<Tool>
     */
    protected function sharedTools(Thread $thread, User $user): array
    {
        return [
            new GetChannelFulfillmentFlowTool($thread, $user),
            new SuggestProfilesForRequestTool($thread, $user),
            new AutoModeSelectorTool($thread, $user),
            new ConversationAuditTool($thread, $user),
            new SessionResetTool($thread, $user),
            new SessionForkTool($thread, $user),
            new SessionTransferTool($thread, $user),
            new SessionMergeSummaryTool($thread, $user),
            new ModePolicyTool($thread, $user),
            new ContextBudgetTool($thread, $user),
            new DualWriteDiffTool($thread, $user),
            new ReplayTool($thread, $user),
            new PrivacyGuardTool($thread, $user),
            new SessionHealthTool($thread, $user),
            new WriteMemoryFileTool($thread, $user),
            ...$this->knowledgeSearchTools($thread),
        ];
    }

    /**
     * @return list<Tool>
     */
    protected function knowledgeSearchTools(Thread $thread): array
    {
        $storeIds = $this->knowledgeSearchStoreResolver->resolveExternalStoreIds($thread);

        if ($storeIds === []) {
            return [];
        }

        return [
            new FileSearch(stores: $storeIds),
        ];
    }

    protected function canCreateRequestFromThread(Thread $thread, User $user): bool
    {
        $threadable = $thread->threadable;

        if (! $threadable instanceof Channel) {
            return false;
        }

        if (! $threadable->hasActor($user)) {
            return false;
        }

        if ($threadable->primaryRequest()) {
            return false;
        }

        return $thread->purpose === Thread::PurposeMain;
    }

    protected function isAsker(ServiceRequest $serviceRequest, User $user): bool
    {
        try {
            return $serviceRequest->hasUserActor($user, ServiceRequest::ActionAsker);
        } catch (\Throwable) {
            $channel = $serviceRequest->channels()->latest('channels.id')->first();

            return $channel ? $channel->hasActor($user) : false;
        }
    }

    protected function isWorker(ServiceRequest $serviceRequest, User $user): bool
    {
        try {
            return $serviceRequest->hasProfileActorForUser($user);
        } catch (\Throwable) {
            return false;
        }
    }
}
