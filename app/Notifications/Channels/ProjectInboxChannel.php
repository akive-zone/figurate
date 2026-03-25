<?php

namespace App\Notifications\Channels;

use App\Features\Actions\Chat\ProjectThreadMessageToInbox;
use App\Models\Server\Message;
use App\Models\Server\User;
use Illuminate\Notifications\Notification;

class ProjectInboxChannel
{
    public function __construct(protected ProjectThreadMessageToInbox $projectThreadMessageToInbox) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toInbox')) {
            return;
        }

        $message = $notification->toInbox($notifiable);

        if (! $message instanceof Message || ! $message->exists) {
            return;
        }

        $this->projectThreadMessageToInbox->execute($notifiable, $message);
    }
}
