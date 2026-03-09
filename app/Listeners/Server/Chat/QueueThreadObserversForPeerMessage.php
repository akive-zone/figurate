<?php

namespace App\Listeners\Server\Chat;

use App\Events\Server\Chat\ThreadMessageStored;
use App\Jobs\ProcessThreadObservers;
use App\Models\Server\Thread;

class QueueThreadObserversForPeerMessage
{
    public function handle(ThreadMessageStored $event): void
    {
        $message = $event->message;
        $messageableType = is_string($message->messageable_type) ? trim($message->messageable_type) : '';
        $threadMorphClass = (new Thread)->getMorphClass();

        if (! in_array($messageableType, [$threadMorphClass, Thread::class], true)) {
            return;
        }

        $meta = is_array($message->meta) ? $message->meta : [];
        if (($meta['source'] ?? null) !== 'peer_message') {
            return;
        }

        if (($meta['observer_dispatch'] ?? true) === false) {
            return;
        }

        ProcessThreadObservers::dispatch((int) $message->messageable_id, (int) $message->id);
    }
}
