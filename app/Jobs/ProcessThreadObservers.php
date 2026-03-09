<?php

namespace App\Jobs;

use App\Ai\Support\Observer\Contracts\ObserverSkill;
use App\Ai\Support\Observer\ObserverRegistry;
use App\Ai\Support\Observer\ObserverResult;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\ThreadEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessThreadObservers implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $threadId,
        public int $messageId,
    ) {
        $this->afterCommit();
    }

    public function handle(ObserverRegistry $registry): void
    {
        $thread = Thread::query()
            ->with(['actors' => fn ($query) => $query
                ->where('role', ThreadActor::RoleObserver)
                ->where('status', ThreadActor::StatusActive)
                ->orderBy('priority')])
            ->find($this->threadId);

        $message = Message::query()->find($this->messageId);

        if (! $thread || ! $message) {
            return;
        }

        $hasPresenter = $thread->actors()
            ->where('role', ThreadActor::RolePresenter)
            ->where('status', ThreadActor::StatusActive)
            ->exists();

        if ($hasPresenter) {
            return;
        }

        $updatedMeta = $message->meta ?? [];
        $messageChanged = false;

        foreach ($thread->actors as $threadActor) {
            $observerSkill = $registry->resolve($threadActor, $thread, $message);

            if (! $observerSkill) {
                continue;
            }

            $result = $this->observeWithSkill($observerSkill);

            if (! $result) {
                continue;
            }

            $thread->events()->create([
                'thread_actor_id' => $threadActor->id,
                'message_id' => $message->id,
                'event_key' => $threadActor->actorReference(),
                'layer' => ThreadEvent::LayerExecution,
                'kind' => ThreadEvent::KindObserver,
                'operation' => $result->eventType,
                'state' => ThreadEvent::StateCompleted,
                'event_type' => $result->eventType,
                'severity' => $result->severity,
                'payload' => $result->payload,
            ]);

            if ($result->eventType === 'moderation_flagged') {
                $updatedMeta['moderation_status'] = 'flagged';
                $updatedMeta['observer_flags'][] = $threadActor->actorReference();
                $messageChanged = true;
            }

            if (
                $result->eventType === 'message_blocked' &&
                (($threadActor->config['mode'] ?? ThreadActor::ModePassive) === ThreadActor::ModeEnforcing) &&
                $result->redactMessage
            ) {
                $updatedMeta['moderation_status'] = 'blocked';
                $updatedMeta['observer_flags'][] = $threadActor->actorReference();
                $message->text = '[Message removed by safety policy]';
                $messageChanged = true;
            }
        }

        if ($messageChanged) {
            $updatedMeta['observer_flags'] = array_values(array_unique($updatedMeta['observer_flags'] ?? []));

            $message->forceFill([
                'meta' => $updatedMeta,
            ])->save();
        }
    }

    protected function observeWithSkill(ObserverSkill $observerSkill): ?ObserverResult
    {
        return $observerSkill->observe();
    }
}
