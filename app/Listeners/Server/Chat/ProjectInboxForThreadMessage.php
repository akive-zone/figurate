<?php

namespace App\Listeners\Server\Chat;

use App\Events\Server\Chat\ThreadMessageStored;
use App\Features\Actions\Conversation\ResolveThreadMessageInboxRecipients;
use App\Notifications\Server\Chat\ThreadMessageNotification;

class ProjectInboxForThreadMessage
{
    public function __construct(protected ResolveThreadMessageInboxRecipients $resolveThreadMessageInboxRecipients) {}

    public function handle(ThreadMessageStored $event): void
    {
        $recipients = $this->resolveThreadMessageInboxRecipients->execute($event->message);

        if ($recipients->isEmpty()) {
            return;
        }

        $recipients->each(function ($recipient) use ($event): void {
            $recipient->notify(new ThreadMessageNotification($event->message));
        });
    }
}
