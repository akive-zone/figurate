<?php

namespace App\Jobs;

use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Support\ThreadObservers\ThreadActorObserverRegistry;
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

    public function handle(ThreadActorObserverRegistry $registry): void
    {
        $thread = Thread::query()
            ->with(['actors' => fn ($query) => $query
                ->where('role', ThreadActor::RoleObserver)
                ->where('status', ThreadActor::StatusActive)
                ->orderBy('priority')])
            ->find($this->threadId);

        $message = Message::query()->find($this->messageId);

        if (! $thread || ! $message || ! $thread->isPeerConversation()) {
            return;
        }

        $updatedMeta = $message->meta ?? [];
        $messageChanged = false;

        foreach ($thread->actors as $threadActor) {
            $observer = $registry->resolve($threadActor);

            if (! $observer) {
                continue;
            }

            $result = $observer->observe($thread, $message);

            if (! $result) {
                continue;
            }

            $thread->events()->create([
                'message_id' => $message->id,
                'actor_key' => $threadActor->actorReference(),
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
                $message->body = '[Message removed by safety policy]';
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
}
