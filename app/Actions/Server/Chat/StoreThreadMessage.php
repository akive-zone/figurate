<?php

namespace App\Actions\Server\Chat;

use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\User;

class StoreThreadMessage
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __invoke(
        Thread $thread,
        ?User $sender,
        ?string $text,
        array $meta = [],
        string $type = 'text',
        ?string $tag = null,
    ): Message {
        return $thread->messages()->create([
            'senderable_type' => $sender?->getMorphClass(),
            'senderable_id' => $sender?->getKey(),
            'type' => $type,
            'tag' => $tag,
            'text' => $text,
            'attachments' => null,
            'meta' => $meta,
        ]);
    }
}
