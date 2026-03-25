<?php

namespace App\Features\Actions\Conversation;

use App\Ai\Storage\ConversationPersistenceResolver;
use App\Ai\Support\Knowledge\MessageAttachmentStoreIngestor;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;

class DispatchThreadMessage
{
    public function __construct(
        protected StoreThreadMessage $storeThreadMessage,
        protected MessageAttachmentStoreIngestor $messageAttachmentStoreIngestor,
    ) {}

    public function execute(ThreadMessageEntry $entry): Post
    {
        if ($entry->authorizeActor && ! $this->canActorWrite($entry->space, $entry->thread, $entry->actor)) {
            abort(403);
        }

        $post = $this->storeThreadMessage->execute(
            thread: $entry->thread,
            sender: $entry->actor,
            text: $entry->text,
            meta: $this->messageMeta($entry),
            type: $entry->type,
            tag: $entry->tag,
        );

        if ($entry->attachments->isNotEmpty()) {
            // Post model stores attachments as inline array
            $attachmentsPayload = $entry->attachments->map(fn (array $attachment) => [
                'name' => pathinfo($attachment['original_name'], PATHINFO_FILENAME),
                'file_name' => $attachment['original_name'],
                'path' => $attachment['path'],
            ])->all();

            $post->forceFill(['attachments' => $attachmentsPayload])->save();

            if ($entry->actor) {
                $this->messageAttachmentStoreIngestor->ingest($entry->thread, $post, $entry->actor);
            }
        }

        return $post;
    }

    protected function canActorWrite(?Space $space, Thread $thread, ?User $actor): bool
    {
        if (! $space || ! $actor) {
            return false;
        }

        if (! $space->conversationThreadIds()->contains($thread->getKey())) {
            return false;
        }

        return $space->hasActor($actor);
    }

    /**
     * @return array<string, mixed>
     */
    protected function messageMeta(ThreadMessageEntry $entry): array
    {
        $meta = [
            ...$entry->meta,
            'source' => $entry->source,
            'observer_dispatch' => $entry->dispatchObservers,
        ];

        if (! array_key_exists('conversation_persistence', $meta)) {
            $conversationPersistence = $this->requestedConversationPersistenceMode();

            if ($conversationPersistence !== null) {
                $meta['conversation_persistence'] = $conversationPersistence;
            }
        }

        return $meta;
    }

    protected function requestedConversationPersistenceMode(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        return ConversationPersistenceResolver::normalizeMode(
            $request?->input('conversation_persistence')
            ?? $request?->header('X-Conversation-Persistence')
        );
    }
}
