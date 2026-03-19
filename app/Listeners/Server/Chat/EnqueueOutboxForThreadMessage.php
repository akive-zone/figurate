<?php

namespace App\Listeners\Server\Chat;

use App\Events\Server\Chat\ThreadMessageStored;
use App\Features\Actions\Chat\EnqueueThreadMessageOutbox;

class EnqueueOutboxForThreadMessage
{
    public function __construct(protected EnqueueThreadMessageOutbox $enqueueThreadMessageOutbox) {}

    public function handle(ThreadMessageStored $event): void
    {
        ($this->enqueueThreadMessageOutbox)($event->message);
    }
}
