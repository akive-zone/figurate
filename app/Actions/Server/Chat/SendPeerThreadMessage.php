<?php

namespace App\Actions\Server\Chat;

use App\Models\Server\Channel;
use App\Models\Server\Message;
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
    public function __invoke(
        Channel $channel,
        Thread $thread,
        User $actor,
        ?string $text,
        Collection $attachments,
        string $source = 'peer_message',
        bool $dispatchObservers = true,
    ): Message {
        return ($this->dispatchThreadMessage)(ThreadMessageEntry::peerMessage(
            channel: $channel,
            thread: $thread,
            actor: $actor,
            text: $text,
            attachments: $attachments,
            source: $source,
            dispatchObservers: $dispatchObservers,
        ));
    }
}
