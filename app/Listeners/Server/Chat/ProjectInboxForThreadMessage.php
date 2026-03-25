<?php

namespace App\Listeners\Server\Chat;

use App\Actions\Server\Chat\ProjectThreadMessageToInbox;
use App\Events\Server\Chat\ThreadMessageStored;

class ProjectInboxForThreadMessage
{
    public function __construct(protected ProjectThreadMessageToInbox $projectThreadMessageToInbox) {}

    public function handle(ThreadMessageStored $event): void
    {
        ($this->projectThreadMessageToInbox)($event->message);
    }
}
