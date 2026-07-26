<?php

namespace App\Notifications\Channels;

use App\Features\Actions\Chat\EnqueueThreadMessageOutbox;
use App\Models\Server\Post;
use App\Models\Server\User;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

class CoordinationChannel
{
    public function __construct(
        protected DatabaseChannel $databaseChannel,
        protected ProjectInboxChannel $projectInboxChannel,
        protected ThreadCoordinationChannel $threadCoordinationChannel,
        protected EnqueueThreadMessageOutbox $enqueueThreadMessageOutbox,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $this->databaseChannel->send($notifiable, $notification);

        if ($notifiable instanceof User && $notifiable->canActAsEndUser()) {
            $this->projectInboxChannel->send($notifiable, $notification);
        }

        if ($notifiable instanceof User && $notifiable->isRobot()) {
            $this->threadCoordinationChannel->send($notifiable, $notification);
        }

        $post = $this->resolveMessage($notifiable, $notification);

        if ($post instanceof Post) {
            $this->enqueueThreadMessageOutbox->execute($post);
        }
    }

    protected function resolveMessage(object $notifiable, Notification $notification): ?Post
    {
        if (method_exists($notification, 'toCoordination')) {
            $coordination = $notification->toCoordination($notifiable);

            if (is_array($coordination) && ($coordination['message'] ?? null) instanceof Post) {
                return $coordination['message'];
            }
        }

        if (method_exists($notification, 'toInbox')) {
            $post = $notification->toInbox($notifiable);

            return $post instanceof Post ? $post : null;
        }

        return null;
    }
}
