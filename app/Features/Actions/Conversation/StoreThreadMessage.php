<?php

namespace App\Features\Actions\Conversation;

use App\Events\Server\Chat\ThreadMessageStored;
use App\Models\Server\Post;
use App\Models\Server\PostRelation;
use App\Models\Server\Thread;
use App\Models\Server\User;

class StoreThreadMessage
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function execute(
        Thread $thread,
        ?User $sender,
        ?string $text,
        array $meta = [],
        string $type = 'text',
        ?string $tag = null,
    ): Post {
        $post = $thread->posts()->create([
            'type' => Post::TypeMessage,
            'tag' => $tag,
            'data' => [
                'text' => $text,
                'message_type' => $type,
            ],
            'meta' => $meta,
        ]);

        if ($sender) {
            PostRelation::query()->create([
                'post_id' => $post->id,
                'role' => 'sender',
                'relationable_type' => $sender->getMorphClass(),
                'relationable_id' => $sender->getKey(),
            ]);
        }

        ThreadMessageStored::dispatch($post);

        return $post;
    }
}
