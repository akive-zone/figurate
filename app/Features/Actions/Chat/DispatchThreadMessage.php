<?php

namespace App\Features\Actions\Chat;

use App\Ai\Support\Knowledge\MessageAttachmentStoreIngestor;
use App\Models\Server\Channel;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\User;

class DispatchThreadMessage
{
    public function __construct(
        protected StoreThreadMessage $storeThreadMessage,
        protected MessageAttachmentStoreIngestor $messageAttachmentStoreIngestor,
    ) {}

    public function execute(ThreadMessageEntry $entry): Message
    {
        if ($entry->authorizeActor && ! $this->canActorWrite($entry->channel, $entry->thread, $entry->actor)) {
            abort(403);
        }

        $message = $this->storeThreadMessage->execute(
            thread: $entry->thread,
            sender: $entry->actor,
            text: $entry->text,
            meta: [
                ...$entry->meta,
                'source' => $entry->source,
                'observer_dispatch' => $entry->dispatchObservers,
            ],
            type: $entry->type,
            tag: $entry->tag,
        );

        $entry->attachments->each(function (array $attachment) use ($message): void {
            $message->addMedia($attachment['path'])
                ->usingName(pathinfo($attachment['original_name'], PATHINFO_FILENAME))
                ->usingFileName($attachment['original_name'])
                ->toMediaCollection('attachments');
        });

        if ($entry->attachments->isNotEmpty()) {
            $message->syncAttachmentPayload();

            if ($entry->actor) {
                $this->messageAttachmentStoreIngestor->ingest($entry->thread, $message, $entry->actor);
            }
        }

        return $message;
    }

    protected function canActorWrite(?Channel $channel, Thread $thread, ?User $actor): bool
    {
        if (! $channel || ! $actor) {
            return false;
        }

        if (! $channel->conversationThreadIds()->contains($thread->getKey())) {
            return false;
        }

        return $channel->hasActor($actor);
    }
}
