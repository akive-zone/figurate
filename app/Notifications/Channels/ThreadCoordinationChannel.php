<?php

namespace App\Notifications\Channels;

use App\Models\Server\Channel;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\ThreadEvent;
use App\Models\Server\User;
use Illuminate\Notifications\Notification;

class ThreadCoordinationChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toCoordination')) {
            return;
        }

        $coordination = $notification->toCoordination($notifiable);

        if (! is_array($coordination)) {
            return;
        }

        $thread = $coordination['thread'] ?? null;
        $message = $coordination['message'] ?? null;
        $channel = $coordination['channel'] ?? null;
        $source = $coordination['source'] ?? null;

        if (! $thread instanceof Thread || ! $message instanceof Message) {
            return;
        }

        $thread->events()->create([
            'thread_actor_id' => $this->resolveThreadActorId($thread, $notifiable),
            'message_id' => $message->id,
            'event_key' => 'notification:coordination:'.$notifiable->getKey(),
            'layer' => ThreadEvent::LayerExecution,
            'kind' => ThreadEvent::KindOrchestration,
            'operation' => 'notification.channel.coordination',
            'state' => ThreadEvent::StateRequested,
            'event_type' => 'orchestration.notification.coordination_requested',
            'severity' => 'low',
            'payload' => [
                'notification' => $notification::class,
                'recipient_user_id' => $notifiable->id,
                'recipient_user_uuid' => $notifiable->uuid,
                'recipient_user_type' => $notifiable->type,
                'message_id' => $message->id,
                'message_ulid' => $message->ulid,
                'thread_uuid' => $thread->uuid,
                'channel_uuid' => $channel instanceof Channel ? $channel->uuid : null,
                'source' => is_string($source) && trim($source) !== '' ? trim($source) : null,
            ],
        ]);
    }

    protected function resolveThreadActorId(Thread $thread, User $recipient): ?int
    {
        return $thread->actors()
            ->where('status', ThreadActor::StatusActive)
            ->where('actorable_type', $recipient->getMorphClass())
            ->where('actorable_id', $recipient->getKey())
            ->value('id');
    }
}
