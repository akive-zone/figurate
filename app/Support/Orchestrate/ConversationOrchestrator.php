<?php

namespace App\Support\Orchestrate;

use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\ThreadEvent;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ConversationOrchestrator
{
    public function resolve(
        Channel $channel,
        User $actor,
        ?int $thread = null,
        ?string $message = null,
    ): OrchestrationDecision {
        return DB::transaction(function () use ($channel, $actor, $thread, $message): OrchestrationDecision {
            $actions = [];
            $resolvedThread = $this->resolveBaseThread(
                channel: $channel,
                actor: $actor,
                thread: $thread,
            );

            if ($thread === null) {
                [$resolvedThread, $triggerActions] = $this->applyPurposeTriggers($channel, $resolvedThread, $message);
                $actions = array_merge($actions, $triggerActions);
            }

            $this->persistActiveState($channel, $actor, $resolvedThread);

            foreach ($actions as $action) {
                $eventType = (string) $action['event_type'];

                $resolvedThread->events()->create([
                    'thread_actor_id' => null,
                    'message_id' => null,
                    'event_key' => 'orchestrator',
                    'layer' => ThreadEvent::LayerExecution,
                    'kind' => ThreadEvent::KindOrchestration,
                    'operation' => str_replace('orchestration.', '', $eventType),
                    'state' => ThreadEvent::StateCompleted,
                    'event_type' => $eventType,
                    'severity' => 'low',
                    'payload' => $action,
                ]);
            }

            $primaryPresenter = $resolvedThread->actors()
                ->where('role', ThreadActor::RolePresenter)
                ->where('status', ThreadActor::StatusActive)
                ->orderBy('priority')
                ->first();
            $hasPresenter = $primaryPresenter !== null;

            return new OrchestrationDecision(
                thread: $resolvedThread,
                responderType: $hasPresenter ? 'presenter' : 'direct',
                responderKey: $primaryPresenter?->actorName(),
                actions: $actions,
            );
        });
    }

    protected function resolveBaseThread(
        Channel $channel,
        User $actor,
        ?int $thread,
    ): Thread {
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

        return $this->createPurposeThread($channel, Thread::PurposeMain);
    }

    /**
     * @return array{0: Thread, 1: list<array<string, mixed>>}
     */
    protected function applyPurposeTriggers(
        Channel $channel,
        Thread $thread,
        ?string $message
    ): array {
        $targetPurpose = $this->detectPurposeFromMessage($message);

        if (! $targetPurpose || $targetPurpose === $thread->purpose) {
            return [$thread, []];
        }

        $openThread = $this->threadsQuery($channel)
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

        $spawnedThread = $this->createPurposeThread($channel, $targetPurpose);

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
                'actorable_type' => $actor->getMorphClass(),
                'actorable_id' => $actor->getKey(),
            ],
            [
                'thread_id' => $thread->id,
                'status' => ChannelActorState::StatusActive,
            ],
        );
    }

    protected function createPurposeThread(Channel $channel, string $purpose): Thread
    {
        $thread = $channel->threads()->create([
            'purpose' => $purpose,
            'title' => $this->defaultTitle($purpose),
            'phase' => $this->defaultPhase($purpose),
            'status' => 'open',
        ]);

        $thread->actors()->create([
            'actorable_type' => $this->defaultHandlerActor($purpose),
            'actorable_id' => null,
            'role' => ThreadActor::RolePresenter,
            'status' => ThreadActor::StatusActive,
            'priority' => 1,
            'config' => null,
        ]);

        return $thread;
    }

    protected function threadsQuery(Channel $channel): Builder
    {
        return Thread::query()->whereIn('id', $channel->conversationThreadIds()->all());
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
