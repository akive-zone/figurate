<?php

namespace App\Jobs;

use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Support\Observer\ObserverRegistry;
use App\Support\Observer\ObserverResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Throwable;

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

        if (! $thread || ! $message || ! $thread->isPeerConversation()) {
            return;
        }

        $updatedMeta = $message->meta ?? [];
        $messageChanged = false;

        foreach ($thread->actors as $threadActor) {
            $observerTool = $registry->resolve($threadActor, $thread, $message);

            if (! $observerTool) {
                continue;
            }

            $result = $this->observeWithTool($observerTool, $threadActor, $message);

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

    protected function observeWithTool(
        Tool $observerTool,
        ThreadActor $threadActor,
        Message $message,
    ): ?ObserverResult {
        try {
            $rawResult = $observerTool->handle(new ToolRequest([
                'message_id' => $message->id,
                'message_body' => $message->body,
                'actor_key' => $threadActor->actorReference(),
                'attachments' => collect($message->attachments ?? [])
                    ->map(fn (mixed $item): string => is_array($item) ? ($item['name'] ?? 'file') : 'file')
                    ->values()
                    ->all(),
            ]));
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        $payload = json_decode((string) $rawResult, true);

        if (! is_array($payload)) {
            return null;
        }

        $eventType = $payload['event_type'] ?? null;
        if (! is_string($eventType) || trim($eventType) === '') {
            return null;
        }

        $severity = $payload['severity'] ?? 'low';
        if (! is_string($severity) || ! in_array($severity, ['low', 'medium', 'high'], true)) {
            $severity = 'low';
        }

        $eventPayload = $payload['payload'] ?? null;
        if (! is_array($eventPayload)) {
            $eventPayload = null;
        }

        return new ObserverResult(
            eventType: $eventType,
            severity: $severity,
            payload: $eventPayload,
            redactMessage: (bool) ($payload['redact_message'] ?? false),
        );
    }
}
