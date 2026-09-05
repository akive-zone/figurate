<?php

namespace App\Features\Actions\Chat;

use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Support\Collection;

class SendPeerThreadMessage
{
    public function __construct(
        protected DispatchThreadMessage $dispatchThreadMessage,
    ) {}

    /**
     * @param  Collection<int, array{path: string, original_name: string}>  $attachments
     */
    public function execute(
        Space $space,
        Thread $thread,
        User $actor,
        ?string $text,
        Collection $attachments,
        string $source = 'peer_message',
    ): Post {
        return $this->dispatchThreadMessage->execute(ThreadMessageEntry::peerMessage(
            space: $space,
            thread: $thread,
            actor: $actor,
            text: $text,
            attachments: $attachments,
            source: $source,
        ));
    }
}
