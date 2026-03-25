<?php

namespace Figurate\FulfillmentManager\Ai\Support;

use App\Ai\Support\ThreadContextResolver;
use App\Models\Server\Post;
use App\Models\Server\Profile;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Figurate\FulfillmentManager\Models\Order;
use Figurate\FulfillmentManager\Models\Quote;
use Illuminate\Database\Eloquent\Builder;

class FulfillmentContext
{
    public const ParticipantRequester = 'asker';

    public const ParticipantTargetProfile = 'target_profile';

    public const ActionAsker = self::ParticipantRequester;

    public const ActionTargetProfile = self::ParticipantTargetProfile;

    public function __construct(
        protected ThreadContextResolver $threadContextResolver = new ThreadContextResolver,
    ) {}

    public function isFulfillmentSubject(mixed $post): bool
    {
        return $post instanceof Post && str_starts_with((string) $post->type, 'request.');
    }

    public function isRequestPost(mixed $post): bool
    {
        return $this->isFulfillmentSubject($post);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createFulfillmentSubject(array $attributes): Post
    {
        $modelClass = $this->requestPostModelClass();

        $created = $modelClass::query()->create($attributes);

        if (! $created instanceof Post) {
            throw new \RuntimeException("Configured request post model [{$modelClass}] must extend ".Post::class);
        }

        return $created;
    }

    public function createRequestPost(array $attributes): Post
    {
        return $this->createFulfillmentSubject($attributes);
    }

    public function resolveSubjectFromThread(Thread $thread): ?Post
    {
        $threadable = $thread->threadable;

        if ($this->isFulfillmentSubject($threadable)) {
            return $threadable;
        }

        $space = $this->threadContextResolver->resolveSpace($thread);

        return $this->resolveSubjectFromSpace($space);
    }

    public function resolveRequestFromThread(Thread $thread): ?Post
    {
        return $this->resolveSubjectFromThread($thread);
    }

    public function resolveSubjectFromSpace(?Space $space): ?Post
    {
        if (! $space) {
            return null;
        }

        $request = $space->primaryRequestPost();

        return $request instanceof Post ? $request : null;
    }

    public function resolveRequestFromSpace(?Space $space): ?Post
    {
        return $this->resolveSubjectFromSpace($space);
    }

    public function isRequester(Post $subjectPost, User $user): bool
    {
        return $this->hasUserActor($subjectPost, $user, self::ParticipantRequester);
    }

    public function hasAsker(Post $requestPost, User $user): bool
    {
        return $this->isRequester($requestPost, $user);
    }

    public function isTargetProfileParticipant(Post $subjectPost, User $user): bool
    {
        if (method_exists($subjectPost, 'hasProfileActorForUser')) {
            try {
                /** @var bool $result */
                $result = $subjectPost->hasProfileActorForUser($user, self::ParticipantTargetProfile);

                return $result;
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    public function hasWorker(Post $requestPost, User $user): bool
    {
        return $this->isTargetProfileParticipant($requestPost, $user);
    }

    public function hasParticipant(Post $subjectPost, User $user): bool
    {
        if (method_exists($subjectPost, 'hasParticipant')) {
            try {
                /** @var bool $result */
                $result = $subjectPost->hasParticipant($user);

                return $result;
            } catch (\Throwable) {
                // Fall through to generic checks.
            }
        }

        if ($this->isRequester($subjectPost, $user) || $this->isTargetProfileParticipant($subjectPost, $user)) {
            return true;
        }

        $space = $this->resolvePrimarySpaceForRequestPost($subjectPost);

        return $space ? $space->hasActor($user) : false;
    }

    public function resolveParticipantProfile(Post $subjectPost, User $user): ?Profile
    {
        if (method_exists($subjectPost, 'participantProfileForUser')) {
            try {
                /** @var Profile|null $profile */
                $profile = $subjectPost->participantProfileForUser($user, self::ParticipantTargetProfile);

                if ($profile instanceof Profile) {
                    return $profile;
                }
            } catch (\Throwable) {
                // Fall through.
            }
        }

        $order = $this->currentOrder($subjectPost);
        $sellerProfile = $order?->sellerProfileRecord();
        if ($sellerProfile instanceof Profile && $sellerProfile->user_id === $user->id) {
            return $sellerProfile;
        }

        $space = $this->resolvePrimarySpaceForRequestPost($subjectPost);

        if ($space instanceof Space) {
            $profile = Profile::query()
                ->where('user_id', $user->id)
                ->where(function (Builder $query): void {
                    $query->where('status', 'approved')
                        ->orWhereNull('status');
                })
                ->latest('id')
                ->first();

            return $profile instanceof Profile ? $profile : null;
        }

        return null;
    }

    public function currentOrder(Post $requestPost): mixed
    {
        return Order::query()
            ->whereHas('relations', function (Builder $query) use ($requestPost): void {
                $query->where('relationable_type', $requestPost->getMorphClass())
                    ->where('relationable_id', $requestPost->getKey())
                    ->where('role', 'request');
            })
            ->latest('id')
            ->first();
    }

    public function quoteForRequest(Post $requestPost, int $quoteId): mixed
    {
        return Quote::query()
            ->whereKey($quoteId)
            ->whereHas('relations', function (Builder $query) use ($requestPost): void {
                $query->where('relationable_type', $requestPost->getMorphClass())
                    ->where('relationable_id', $requestPost->getKey())
                    ->where('role', 'request');
            })
            ->first();
    }

    public function attachAsker(Post $requestPost, User $actor): void
    {
        if (! method_exists($requestPost, 'relatedQuery') || ! method_exists($requestPost, 'attachRelation')) {
            return;
        }

        $alreadyAttached = $requestPost->relatedQuery(User::class, self::ActionAsker)
            ->whereKey($actor->getKey())
            ->exists();

        if (! $alreadyAttached) {
            $requestPost->attachRelation($actor, self::ActionAsker);
        }
    }

    public function hasUserActor(Post $requestPost, User $user, string $action): bool
    {
        if (method_exists($requestPost, 'hasUserActor')) {
            try {
                /** @var bool $result */
                $result = $requestPost->hasUserActor($user, $action);

                return $result;
            } catch (\Throwable) {
                // fall through to space fallback
            }
        }

        $space = $this->resolvePrimarySpaceForRequestPost($requestPost);

        return $space ? $space->hasActor($user) : false;
    }

    public function title(Post $requestPost): ?string
    {
        return data_get($requestPost->payload, 'title');
    }

    public function description(Post $requestPost): ?string
    {
        return data_get($requestPost->payload, 'description');
    }

    /**
     * @return class-string<Post>
     */
    protected function requestPostModelClass(): string
    {
        $configured = config('ai-domain.post_models.request', Post::class);

        return is_string($configured) && is_subclass_of($configured, Post::class)
            ? $configured
            : Post::class;
    }

    protected function resolvePrimarySpaceForRequestPost(Post $requestPost): ?Space
    {
        if (method_exists($requestPost, 'spaces')) {
            try {
                /** @var Space|null $space */
                $space = $requestPost->spaces()->latest('spaces.id')->first();

                if ($space instanceof Space) {
                    return $space;
                }
            } catch (\Throwable) {
                // fall back to related records
            }
        }

        $directSpace = $requestPost->relatedOne(Space::class);

        return $directSpace instanceof Space ? $directSpace : null;
    }
}
