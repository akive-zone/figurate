<?php

namespace App\Support\Conversation;

use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Illuminate\Support\Facades\DB;

class ConversationOrchestrator
{
    public function resolve(
        Channel $channel,
        ServiceRequest $serviceRequest,
        User $actor,
        ?int $requestedThreadId = null,
        ?string $message = null,
    ): OrchestrationDecision {
        return DB::transaction(function () use ($channel, $serviceRequest, $actor, $requestedThreadId, $message): OrchestrationDecision {
            $actions = [];
            $thread = $this->resolveBaseThread(
                serviceRequest: $serviceRequest,
                channel: $channel,
                actor: $actor,
                requestedThreadId: $requestedThreadId,
            );

            if ($requestedThreadId === null) {
                [$thread, $triggerActions] = $this->applyPurposeTriggers($serviceRequest, $thread, $message);
                $actions = array_merge($actions, $triggerActions);
            }

            $this->persistActiveState($channel, $actor, $thread);

            foreach ($actions as $action) {
                $thread->events()->create([
                    'message_id' => null,
                    'actor_key' => 'orchestrator',
                    'event_type' => (string) $action['event_type'],
                    'severity' => 'low',
                    'payload' => $action,
                ]);
            }

            $primaryHandler = $thread->primaryHandlerActor()->first();
            $isHuman = $primaryHandler?->isNamedActor(ThreadActor::ActorHumanChat) ?? false;

            return new OrchestrationDecision(
                thread: $thread,
                responderType: $isHuman ? 'human' : 'agent',
                responderKey: $primaryHandler?->actorName(),
                actions: $actions,
            );
        });
    }

    protected function resolveBaseThread(
        ServiceRequest $serviceRequest,
        Channel $channel,
        User $actor,
        ?int $requestedThreadId,
    ): Thread {
        if ($requestedThreadId !== null) {
            $thread = $serviceRequest->threads()
                ->where('status', 'open')
                ->whereKey($requestedThreadId)
                ->first();

            if (! $thread) {
                abort(404);
            }

            return $thread;
        }

        $actorState = ChannelActorState::query()
            ->where('channel_id', $channel->id)
            ->where('actor_type', $actor->getMorphClass())
            ->where('actor_id', $actor->getKey())
            ->first();

        if ($actorState?->thread_id) {
            $thread = $serviceRequest->threads()
                ->where('status', 'open')
                ->whereKey($actorState->thread_id)
                ->first();

            if ($thread) {
                return $thread;
            }
        }

        $thread = $serviceRequest->threads()
            ->where('status', 'open')
            ->where('purpose', Thread::PurposeMain)
            ->orderBy('id')
            ->first();

        if ($thread) {
            return $thread;
        }

        $thread = $serviceRequest->threads()
            ->where('status', 'open')
            ->latest('id')
            ->first();

        if ($thread) {
            return $thread;
        }

        return $this->createPurposeThread(
            serviceRequest: $serviceRequest,
            purpose: Thread::PurposeMain,
        );
    }

    /**
     * @return array{0: Thread, 1: list<array<string, mixed>>}
     */
    protected function applyPurposeTriggers(ServiceRequest $serviceRequest, Thread $thread, ?string $message): array
    {
        $targetPurpose = $this->detectPurposeFromMessage($message);

        if (! $targetPurpose || $targetPurpose === $thread->purpose) {
            return [$thread, []];
        }

        $openThread = $serviceRequest->threads()
            ->where('status', 'open')
            ->where('purpose', $targetPurpose)
            ->latest('id')
            ->first();

        if ($openThread) {
            return [$openThread, [[
                'event_type' => 'orchestration.thread_switched',
                'trigger' => 'message_intent',
                'to_thread_id' => $openThread->id,
                'purpose' => $targetPurpose,
            ]]];
        }

        $spawnedThread = $this->createPurposeThread($serviceRequest, $targetPurpose);

        return [$spawnedThread, [[
            'event_type' => 'orchestration.thread_spawned',
            'trigger' => 'message_intent',
            'to_thread_id' => $spawnedThread->id,
            'purpose' => $targetPurpose,
        ]]];
    }

    protected function persistActiveState(Channel $channel, User $actor, Thread $thread): void
    {
        ChannelActorState::query()->updateOrCreate(
            [
                'channel_id' => $channel->id,
                'actor_type' => $actor->getMorphClass(),
                'actor_id' => $actor->getKey(),
            ],
            [
                'thread_id' => $thread->id,
                'status' => ChannelActorState::StatusActive,
            ],
        );
    }

    protected function createPurposeThread(ServiceRequest $serviceRequest, string $purpose): Thread
    {
        $thread = $serviceRequest->threads()->create([
            'purpose' => $purpose,
            'title' => $this->defaultTitle($purpose),
            'phase' => $this->defaultPhase($purpose),
            'status' => 'open',
        ]);

        $thread->actors()->create([
            'actorable_type' => $this->defaultHandlerActor($purpose),
            'actorable_id' => null,
            'role' => ThreadActor::RoleHandler,
            'status' => ThreadActor::StatusActive,
            'priority' => 1,
            'config' => null,
        ]);

        return $thread;
    }

    protected function detectPurposeFromMessage(?string $message): ?string
    {
        $content = mb_strtolower(trim((string) $message));

        if ($content === '') {
            return null;
        }

        if (preg_match('/\b(dispute|refund|complaint|fraud|scam|chargeback)\b/u', $content) === 1) {
            return Thread::PurposeDispute;
        }

        if (preg_match('/\b(payment|invoice|billing|bill|cost|price)\b/u', $content) === 1) {
            return Thread::PurposeBilling;
        }

        if (preg_match('/\b(start work|begin work|execution|deliver|in progress|kickoff)\b/u', $content) === 1) {
            return Thread::PurposeExecution;
        }

        if (preg_match('/\b(plan|planning|scope|breakdown|steps)\b/u', $content) === 1) {
            return Thread::PurposePlanning;
        }

        return null;
    }

    protected function defaultHandlerActor(string $purpose): string
    {
        return match ($purpose) {
            Thread::PurposeExecution, Thread::PurposeBilling => ThreadActor::ActorOrderAgent,
            default => ThreadActor::ActorRequestAgent,
        };
    }

    protected function defaultTitle(string $purpose): string
    {
        return match ($purpose) {
            Thread::PurposePlanning => 'Planning',
            Thread::PurposeExecution => 'Execution',
            Thread::PurposeBilling => 'Billing',
            Thread::PurposeDispute => 'Dispute',
            Thread::PurposeSupport => 'Support',
            Thread::PurposeSystem => 'System',
            default => 'Project Main',
        };
    }

    protected function defaultPhase(string $purpose): string
    {
        return match ($purpose) {
            Thread::PurposePlanning => 'scope_planning',
            Thread::PurposeExecution => 'order_kickoff',
            Thread::PurposeBilling => 'billing_review',
            Thread::PurposeDispute => 'opened',
            Thread::PurposeSupport => 'support_open',
            Thread::PurposeSystem => 'system_open',
            default => 'request_intake',
        };
    }
}
