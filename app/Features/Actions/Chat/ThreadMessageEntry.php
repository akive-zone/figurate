<?php

namespace App\Features\Actions\Chat;

use App\Models\Server\Space;
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
        public ?Space $space,
        public ?User $actor,
        public ?string $text,
        public Collection $attachments,
        public string $source,
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
        Space $space,
        Thread $thread,
        User $actor,
        ?string $text,
        Collection $attachments,
        string $source = 'peer_message',
        array $meta = [],
    ): self {
        return new self(
            thread: $thread,
            space: $space,
            actor: $actor,
            text: $text,
            attachments: $attachments,
            source: $source,
            authorizeActor: true,
            meta: $meta,
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function systemMessage(
        Thread $thread,
        ?string $text,
        array $meta = [],
        string $source = 'system_message',
        string $type = 'text',
        ?string $tag = null,
    ): self {
        return new self(
            thread: $thread,
            space: null,
            actor: null,
            text: $text,
            attachments: collect(),
            source: $source,
            authorizeActor: false,
            meta: $meta,
            type: $type,
            tag: $tag,
        );
    }
}
