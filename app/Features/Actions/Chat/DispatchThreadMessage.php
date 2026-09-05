<?php

namespace App\Features\Actions\Chat;

use App\Events\Server\Chat\PreparingThreadMessage;
use App\Events\Server\Chat\ThreadMessageDispatched;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;

class DispatchThreadMessage
{
    public function __construct(protected StoreThreadMessage $storeThreadMessage) {}

    public function execute(ThreadMessageEntry $entry): Post
    {
        if ($entry->authorizeActor && ! $this->canActorWrite($entry->space, $entry->thread, $entry->actor)) {
            abort(403);
        }

        $preparing = new PreparingThreadMessage($entry, $this->messageMeta($entry));
        event($preparing);

        $post = $this->storeThreadMessage->execute(
            thread: $entry->thread,
            sender: $entry->actor,
            text: $entry->text,
            meta: $preparing->meta,
            type: $entry->type,
            tag: $entry->tag,
        );

        if ($entry->attachments->isNotEmpty()) {
            foreach ($entry->attachments as $attachment) {
                $post->addMedia($attachment['path'])
                    ->usingName(pathinfo($attachment['original_name'], PATHINFO_FILENAME))
                    ->usingFileName($attachment['original_name'])
                    ->toMediaCollection(Post::AttachmentCollection);
            }

            $post->unsetRelation('media');
            $post->refresh();

        }

        ThreadMessageDispatched::dispatch($post);

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
        ];

        return $meta;
    }
}
