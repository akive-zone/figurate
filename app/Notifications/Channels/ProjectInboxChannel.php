<?php

namespace App\Notifications\Channels;

use App\Features\Actions\Chat\ProjectThreadMessageToInbox;
use App\Models\Server\Post;
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

        $post = $notification->toInbox($notifiable);

        if (! $post instanceof Post || ! $post->exists) {
            return;
        }

        $this->projectThreadMessageToInbox->execute($notifiable, $post);
    }
}
