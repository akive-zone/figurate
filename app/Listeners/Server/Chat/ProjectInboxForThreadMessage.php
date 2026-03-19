<?php

namespace App\Listeners\Server\Chat;

use App\Events\Server\Chat\ThreadMessageStored;
use App\Features\Actions\Chat\ProjectThreadMessageToInbox;
use App\Features\Actions\Chat\ResolveThreadMessageInboxRecipients;

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
