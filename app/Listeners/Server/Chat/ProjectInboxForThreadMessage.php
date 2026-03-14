<?php

namespace App\Listeners\Server\Chat;

use App\Actions\Server\Chat\ProjectThreadMessageToInbox;
use App\Actions\Server\Chat\ResolveThreadMessageInboxRecipients;
use App\Events\Server\Chat\ThreadMessageStored;

class ProjectInboxForThreadMessage
{
    public function __construct(
        protected ResolveThreadMessageInboxRecipients $resolveThreadMessageInboxRecipients,
        protected ProjectThreadMessageToInbox $projectThreadMessageToInbox,
    ) {}

    public function handle(ThreadMessageStored $event): void
    {
        $recipients = ($this->resolveThreadMessageInboxRecipients)($event->message);

        if ($recipients->isEmpty()) {
            return;
        }

        $recipients->each(function ($recipient) use ($event): void {
            ($this->projectThreadMessageToInbox)($recipient, $event->message);
        });
    }
}
