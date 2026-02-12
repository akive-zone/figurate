<?php

namespace App\Ai\Support;

use App\Ai\Tools\AcknowledgeAssessmentTool;
use App\Ai\Tools\CreateOrderFromQuoteTool;
use App\Ai\Tools\UpsertAssessmentTool;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Laravel\Ai\Contracts\Tool;

class ThreadToolResolver
{
    /**
     * @return list<Tool>
     */
    public function resolve(Thread $thread, User $user): array
    {
        $serviceRequest = $thread->threadable;

        if (! $serviceRequest instanceof ServiceRequest) {
            return [];
        }

        $isAsker = $serviceRequest->hasUserActor($user, ServiceRequest::ActionAsker);
        $isWorker = $serviceRequest->hasProfileActorForUser($user);
        $primaryActor = $thread->primaryHandlerActor()->first()?->actorName();

        if ($primaryActor === ThreadActor::ActorRequestAgent) {
            return $isAsker
                ? [
                    new CreateOrderFromQuoteTool($thread, $serviceRequest, $user),
                ]
                : [];
        }

        if ($primaryActor === ThreadActor::ActorOrderAgent) {
            $tools = [];

            if ($isAsker) {
                $tools[] = new AcknowledgeAssessmentTool($thread, $serviceRequest, $user);
            }

            if ($isWorker) {
                $tools[] = new UpsertAssessmentTool($thread, $serviceRequest, $user);
            }

            return $tools;
        }

        return [];
    }
}
