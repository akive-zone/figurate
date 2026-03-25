<?php

namespace Figurate\FulfillmentManager\Models;

use App\Models\Server\Message;
use App\Models\Server\Post;
use App\Models\Server\PostRelation;
use App\Models\Server\Profile;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Figurate\FulfillmentManager\Database\Factories\RequestFactory;
use Figurate\FulfillmentManager\Models\Concerns\HasPostMorphType;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

#[UseFactory(RequestFactory::class)]
class Request extends Post
{
    use HasPostMorphType;

    protected $table = 'posts';

    public const ActionAsker = 'asker';

    public const ActionTargetProfile = 'target_profile';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'postable_type',
        'postable_id',
        'type',
        'status',
        'payload',
        'meta',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('request_type', function (Builder $builder): void {
            $builder->where($builder->getModel()->qualifyColumn('type'), 'like', 'request.%');
        });

        static::creating(function (Request $request): void {
            if (! $request->type) {
                $request->type = 'request.created';
            }

            if (! $request->occurred_at) {
                $request->occurred_at = now();
            }
        });
    }

    public function askers(): Builder
    {
        return $this->relatedQuery(User::class, self::ActionAsker);
    }

    public function targetProfiles(): Builder
    {
        return Profile::query()->whereIn('profiles.id', $this->participantProfileIds()->all());
    }

    public function spaces(): MorphToMany
    {
        return $this->morphToMany(
            Space::class,
            'relationable',
            'space_relations',
            'relationable_id',
            'space_id'
        )->withTimestamps();
    }

    public function messages(): MorphMany
    {
        return $this->morphMany(Message::class, 'messageable');
    }

    public function latestMessage(): MorphOne
    {
        return $this->morphOne(Message::class, 'messageable')->latestOfMany();
    }

    public function threads(): MorphMany
    {
        return $this->morphMany(Thread::class, 'threadable');
    }

    public function quotes(): Builder
    {
        return Quote::query()->whereHas('relations', function (Builder $query): void {
            $query->where('relationable_type', $this->getMorphClass())
                ->where('relationable_id', $this->getKey())
                ->where('role', 'request');
        });
    }

    public function currentOrder(): ?Order
    {
        return Order::query()->whereHas('relations', function (Builder $query): void {
            $query->where('relationable_type', $this->getMorphClass())
                ->where('relationable_id', $this->getKey())
                ->where('role', 'request');
        })->latest('id')->first();
    }

    public function hasOrder(): bool
    {
        return $this->currentOrder() !== null;
    }

    public function hasUserActor(User $user, ?string $action = null): bool
    {
        if ($action !== null && $action !== self::ActionAsker) {
            return false;
        }

        if ($this->askers()->whereKey($user->id)->exists()) {
            return true;
        }

        return $this->hasSpaceActorFallback($user, $action);
    }

    public function hasProfileActorForUser(User $user, ?string $action = null): bool
    {
        if ($action !== null && $action !== self::ActionTargetProfile) {
            return false;
        }

        return $this->targetProfiles()
            ->where('profiles.user_id', $user->id)
            ->exists();
    }

    public function hasParticipant(User $user): bool
    {
        if ($this->hasUserActor($user)) {
            return true;
        }

        if ($this->hasProfileActorForUser($user)) {
            return true;
        }

        return $this->hasSpaceActorFallback($user);
    }

    public function primaryRequester(): ?User
    {
        return $this->askers()->first();
    }

    public function participantProfileForUser(User $user, ?string $action = null): ?Profile
    {
        if ($action !== null && $action !== self::ActionTargetProfile) {
            return null;
        }

        /** @var Profile|null $profile */
        $profile = $this->targetProfiles()
            ->where('profiles.user_id', $user->id)
            ->latest('profiles.id')
            ->first();

        return $profile;
    }

    protected function hasSpaceActorFallback(User $user, ?string $action = null): bool
    {
        if ($action !== null && $action !== self::ActionAsker) {
            return false;
        }

        return $this->spaces()->whereHas('actorStates', function (Builder $query) use ($user): void {
            $query->where('actorable_type', $user->getMorphClass())
                ->where('actorable_id', $user->getKey());
        })->exists();
    }

    public function getTitleAttribute(): ?string
    {
        return data_get($this->payload, 'title');
    }

    public function getDescriptionAttribute(): ?string
    {
        return data_get($this->payload, 'description');
    }

    public function setTitleAttribute(?string $value): void
    {
        $this->putPayloadValue('title', $value);
    }

    public function setDescriptionAttribute(?string $value): void
    {
        $this->putPayloadValue('description', $value);
    }

    /**
     * @return Collection<int, int>
     */
    protected function participantProfileIds(): Collection
    {
        $profileMorphClass = (new Profile)->getMorphClass();
        $profileIds = collect();

        $profileIds = $profileIds->merge(
            $this->relatedQuery(Profile::class, self::ActionTargetProfile)
                ->select('profiles.id')
                ->pluck('profiles.id'),
        );

        $profileIds = $profileIds->merge(
            PostRelation::query()
                ->where('relationable_type', $profileMorphClass)
                ->where('role', 'profile')
                ->whereIn('post_id', $this->quotes()->select('posts.id'))
                ->pluck('relationable_id'),
        );

        $sellerProfileId = $this->currentOrder()?->seller_profile_id;
        if (is_numeric($sellerProfileId)) {
            $profileIds->push((int) $sellerProfileId);
        }

        return $profileIds
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values();
    }
}
