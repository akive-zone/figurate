<?php

namespace App\Ai\Support;

use App\Ai\Tools\AcknowledgeAssessmentTool;
use App\Ai\Tools\CreateOrderFromQuoteTool;
use App\Ai\Tools\CreateRequestFromConversationTool;
use App\Ai\Tools\GetChannelFulfillmentFlowTool;
use App\Ai\Tools\SuggestProfilesForRequestTool;
use App\Ai\Tools\UpsertAssessmentTool;
use App\Models\Server\Channel;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Laravel\Ai\Contracts\Tool;

class ChatToolResolver
{
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
