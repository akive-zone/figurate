<?php

namespace App\Notifications\Channels;

use App\Ai\Storage\ConversationPersistenceResolver;
use App\Notifications\Support\ThreadPromptTransport;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

class CompletionChannel
{
    public function __construct(
        protected DatabaseChannel $databaseChannel,
        protected ThreadPromptTransport $threadPromptTransport,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $this->databaseChannel->send($notifiable, $notification);

        $this->threadPromptTransport->send(
            notifiable: $notifiable,
            notification: $notification,
            conversationPersistenceMode: ConversationPersistenceResolver::ThreadCompletion,
        );
    }
}
