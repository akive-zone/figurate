<?php

namespace App\Notifications\Channels;

use App\Features\Actions\Conversation\EnqueueThreadMessageOutbox;
use App\Models\Server\Message;
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

        $message = $this->resolveMessage($notifiable, $notification);

        if ($message instanceof Message) {
            $this->enqueueThreadMessageOutbox->execute($message);
        }
    }

    protected function resolveMessage(object $notifiable, Notification $notification): ?Message
    {
        if (method_exists($notification, 'toCoordination')) {
            $coordination = $notification->toCoordination($notifiable);

            if (is_array($coordination) && ($coordination['message'] ?? null) instanceof Message) {
                return $coordination['message'];
            }
        }

        if (method_exists($notification, 'toInbox')) {
            $message = $notification->toInbox($notifiable);

            return $message instanceof Message ? $message : null;
        }

        return null;
    }
}
