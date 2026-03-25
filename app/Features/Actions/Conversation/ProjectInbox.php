<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Inbox;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\ThreadEvent;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Model;

class ProjectInbox
{
    public function project(
        User $user,
        Model $inboxable,
        string $kind,
        string $title,
        ?string $summary = null,
        ?string $source = null,
        array $payload = [],
        ?Thread $thread = null,
    ): ?Inbox {
        if (! $user->exists || ! $inboxable->exists) {
            return null;
        }

        return $this->persistInbox(
            $user,
            $inboxable,
            $thread ?? $this->resolveThreadContext($inboxable),
            [
                'kind' => $kind,
                'status' => Inbox::StatusUnread,
                'title' => $title,
                'summary' => $summary,
                'source' => $source,
                'payload' => $payload,
                'read_at' => null,
                'archived_at' => null,
            ],
        );
    }

    /**
     * @param  array{
     *     kind: string,
     *     status: string,
     *     title: string,
     *     summary: ?string,
     *     source: ?string,
     *     payload: array<mixed>,
     *     read_at: mixed,
     *     archived_at: mixed
     * }  $attributes
     */
    protected function persistInbox(User $user, Model $inboxable, ?Thread $thread, array $attributes): Inbox
    {
        return Inbox::query()->firstOrCreate(
            [
                'user_id' => $user->getKey(),
                'inboxable_type' => $inboxable->getMorphClass(),
                'inboxable_id' => $inboxable->getKey(),
                'kind' => $attributes['kind'],
            ],
            [
                'thread_id' => $thread?->getKey(),
                'status' => $attributes['status'],
                'title' => $attributes['title'],
                'summary' => $attributes['summary'],
                'source' => $attributes['source'],
                'payload' => $attributes['payload'] !== [] ? $attributes['payload'] : null,
                'read_at' => $attributes['read_at'],
                'archived_at' => $attributes['archived_at'],
            ],
        );
    }

    protected function resolveThreadContext(Model $inboxable): ?Thread
    {
        if ($inboxable instanceof Thread) {
            return $inboxable;
        }

        if ($inboxable instanceof Post) {
            if ($inboxable->relationLoaded('postable') && $inboxable->postable instanceof Thread) {
                return $inboxable->postable;
            }

            $threadMorphClass = (new Thread)->getMorphClass();
            $postableType = is_string($inboxable->postable_type) ? trim($inboxable->postable_type) : '';

            if (! in_array($postableType, [$threadMorphClass, Thread::class], true)) {
                return null;
            }

            return Thread::query()->find($inboxable->postable_id);
        }

        if ($inboxable instanceof ThreadEvent) {
            if ($inboxable->relationLoaded('thread') && $inboxable->thread instanceof Thread) {
                return $inboxable->thread;
            }

            if (! $inboxable->thread_id) {
                return null;
            }

            return Thread::query()->find($inboxable->thread_id);
        }

        return null;
    }
}
