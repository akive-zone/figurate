<?php

namespace App\Features\Actions\Conversation;

use App\Contracts\Users\UserRepository;
use App\Models\Server\Message;
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
    public function execute(Message $message): Collection
    {
        $thread = $this->resolveThread($message);

        if (! $thread || ! $message->exists) {
            return collect();
        }

        $senderUserId = $this->senderUserId($message);

        return $this->resolveRecipients($thread)
            ->reject(fn (User $user): bool => $senderUserId !== null && (int) $user->getKey() === $senderUserId)
            ->values();
    }

    protected function resolveThread(Message $message): ?Thread
    {
        if ($message->relationLoaded('messageable') && $message->messageable instanceof Thread) {
            return $message->messageable;
        }

        $threadMorphClass = (new Thread)->getMorphClass();
        $messageableType = is_string($message->messageable_type) ? trim($message->messageable_type) : '';

        if (! in_array($messageableType, [$threadMorphClass, Thread::class], true)) {
            return null;
        }

        return Thread::query()->find($message->messageable_id);
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

    protected function senderUserId(Message $message): ?int
    {
        $senderType = is_string($message->senderable_type) ? trim($message->senderable_type) : '';
        $userMorphClass = (new User)->getMorphClass();

        if (! in_array($senderType, [$userMorphClass, User::class], true) || $message->senderable_id === null) {
            return null;
        }

        return (int) $message->senderable_id;
    }
}
