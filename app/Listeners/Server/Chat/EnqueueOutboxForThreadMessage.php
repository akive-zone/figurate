<?php

namespace App\Listeners\Server\Chat;

use App\Actions\Server\Chat\EnqueueThreadMessageOutbox;
use App\Events\Server\Chat\ThreadMessageStored;

class EnqueueOutboxForThreadMessage
{
    public function __construct(protected EnqueueThreadMessageOutbox $enqueueThreadMessageOutbox) {}

    public function handle(ThreadMessageStored $event): void
    {
        ($this->enqueueThreadMessageOutbox)($event->message);
    }
}
