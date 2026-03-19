<?php

namespace App\Features\Actions\Chat;

use App\Models\Server\Channel;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Support\Collection;

readonly class ThreadMessageEntry
{
    /**
     * @param  Collection<int, array{path: string, original_name: string}>  $attachments
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public Thread $thread,
        public ?Channel $channel,
        public ?User $actor,
        public ?string $text,
        public Collection $attachments,
        public string $source,
        public bool $dispatchObservers,
        public bool $authorizeActor,
        public array $meta = [],
        public string $type = 'text',
        public ?string $tag = null,
    ) {}

    /**
     * @param  Collection<int, array{path: string, original_name: string}>  $attachments
     * @param  array<string, mixed>  $meta
     */
    public static function peerMessage(
        Channel $channel,
        Thread $thread,
        User $actor,
        ?string $text,
        Collection $attachments,
        string $source = 'peer_message',
        bool $dispatchObservers = true,
        array $meta = [],
    ): self {
        return new self(
            thread: $thread,
            channel: $channel,
            actor: $actor,
            text: $text,
            attachments: $attachments,
            source: $source,
            dispatchObservers: $dispatchObservers,
            authorizeActor: true,
            meta: $meta,
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function agentMessage(
        Thread $thread,
        ?string $text,
        array $meta = [],
        string $source = 'agent_response',
        string $type = 'text',
        ?string $tag = null,
    ): self {
        return new self(
            thread: $thread,
            channel: null,
            actor: null,
            text: $text,
            attachments: collect(),
            source: $source,
            dispatchObservers: false,
            authorizeActor: false,
            meta: $meta,
            type: $type,
            tag: $tag,
        );
    }
}
