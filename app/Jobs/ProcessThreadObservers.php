<?php

namespace App\Jobs;

use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadObserver;
use App\Support\ThreadObservers\ThreadObserverRegistry;
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

    public function handle(ThreadObserverRegistry $registry): void
    {
        $thread = Thread::query()
            ->with(['observers' => fn ($query) => $query->where('status', 'active')])
            ->find($this->threadId);

        $message = Message::query()->find($this->messageId);

        if (! $thread || ! $message || $thread->agent_key !== Thread::AgentHumanChat) {
            return;
        }

        $updatedMeta = $message->meta ?? [];
        $messageChanged = false;

        foreach ($thread->observers as $threadObserver) {
            $observer = $registry->resolve($threadObserver->observer_key);

            if (! $observer) {
                continue;
            }

            $result = $observer->observe($thread, $message);

            if (! $result) {
                continue;
            }

            $thread->events()->create([
                'message_id' => $message->id,
                'observer_key' => $threadObserver->observer_key,
                'event_type' => $result->eventType,
                'severity' => $result->severity,
                'payload' => $result->payload,
            ]);

            if ($result->eventType === 'moderation_flagged') {
                $updatedMeta['moderation_status'] = 'flagged';
                $updatedMeta['observer_flags'][] = $threadObserver->observer_key;
                $messageChanged = true;
            }

            if (
                $result->eventType === 'message_blocked' &&
                $threadObserver->mode === ThreadObserver::ModeEnforcing &&
                $result->redactMessage
            ) {
                $updatedMeta['moderation_status'] = 'blocked';
                $updatedMeta['observer_flags'][] = $threadObserver->observer_key;
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
