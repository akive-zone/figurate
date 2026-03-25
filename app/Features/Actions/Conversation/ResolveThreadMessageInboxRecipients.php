<?php

namespace App\Features\Actions\Conversation;

use App\Contracts\Users\UserRepository;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Illuminate\Support\Collection;

class ResolveThreadMessageInboxRecipients
{
    public function __construct(protected ?UserRepository $userRepository = null) {}

    /**
     * @return Collection<int, User>
     */
    public function execute(Post $post): Collection
    {
        $thread = $this->resolveThread($post);

        if (! $thread || ! $post->exists) {
            return collect();
        }

        $senderUserId = $this->senderUserId($post);

        return $this->resolveRecipients($thread)
            ->reject(fn (User $user): bool => $senderUserId !== null && (int) $user->getKey() === $senderUserId)
            ->values();
    }

    protected function resolveThread(Post $post): ?Thread
    {
        if ($post->relationLoaded('postable') && $post->postable instanceof Thread) {
            return $post->postable;
        }

        $threadMorphClass = (new Thread)->getMorphClass();
        $postableType = is_string($post->postable_type) ? trim($post->postable_type) : '';

        if (! in_array($postableType, [$threadMorphClass, Thread::class], true)) {
            return null;
        }

        return Thread::query()->find($post->postable_id);
    }

    /**
     * @return Collection<int, User>
     */
    protected function resolveRecipients(Thread $thread): Collection
    {
        $userMorphClasses = [(new User)->getMorphClass(), User::class];

        if ($thread->relationLoaded('actors')) {
            return $thread->actors
                ->filter(function (mixed $actor) use ($userMorphClasses): bool {
                    return $actor instanceof ThreadActor
                        && $actor->status === ThreadActor::StatusActive
                        && $actor->actorable_id !== null
                        && in_array($actor->actorable_type, $userMorphClasses, true)
                        && $actor->relationLoaded('actorable')
                        && $actor->actorable instanceof User;
                })
                ->map(fn (ThreadActor $actor): User => $actor->actorable)
                ->unique(fn (User $user): int => (int) $user->getKey())
                ->values();
        }

        $recipientIds = $thread->actors()
            ->where('status', ThreadActor::StatusActive)
            ->whereIn('actorable_type', $userMorphClasses)
            ->whereNotNull('actorable_id')
            ->pluck('actorable_id')
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            return collect();
        }

        return $this->userRepository()->findManyByIds($recipientIds->all());
    }

    protected function userRepository(): UserRepository
    {
        return $this->userRepository ?? app(UserRepository::class);
    }

    protected function senderUserId(Post $post): ?int
    {
        $senderType = is_string($post->senderable_type) ? trim($post->senderable_type) : '';
        $userMorphClass = (new User)->getMorphClass();

        if (! in_array($senderType, [$userMorphClass, User::class], true) || $post->senderable_id === null) {
            return null;
        }

        return (int) $post->senderable_id;
    }
}
