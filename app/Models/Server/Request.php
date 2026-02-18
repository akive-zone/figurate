<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\QueryException;

class Request extends Post
{
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

    public function users(): MorphToMany
    {
        return $this->morphedByMany(User::class, 'actor', 'request_actors')
            ->withPivot(['action', 'status'])
            ->withTimestamps();
    }

    public function profiles(): MorphToMany
    {
        return $this->morphedByMany(Profile::class, 'actor', 'request_actors')
            ->withPivot(['action', 'status'])
            ->withTimestamps();
    }

    public function channels(): MorphToMany
    {
        return $this->morphToMany(
            Channel::class,
            'relationable',
            'channel_relations',
            'relationable_id',
            'channel_id'
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
        try {
            $query = $this->users()->whereKey($user->id);

            if ($action !== null) {
                $query->wherePivot('action', $action);
            }

            return $query->exists();
        } catch (QueryException) {
            return $this->hasChannelActorFallback($user, $action);
        }
    }

    public function hasProfileActorForUser(User $user, ?string $action = null): bool
    {
        try {
            $query = $this->profiles()->where('profiles.user_id', $user->id);

            if ($action !== null) {
                $query->wherePivot('action', $action);
            }

            return $query->exists();
        } catch (QueryException) {
            return false;
        }
    }

    public function hasParticipant(User $user): bool
    {
        if ($this->hasUserActor($user)) {
            return true;
        }

        if ($this->hasProfileActorForUser($user)) {
            return true;
        }

        return $this->hasChannelActorFallback($user);
    }

    public function primaryRequester(): ?User
    {
        return $this->users()
            ->wherePivot('action', self::ActionAsker)
            ->first();
    }

    protected function hasChannelActorFallback(User $user, ?string $action = null): bool
    {
        if ($action !== null && $action !== self::ActionAsker) {
            return false;
        }

        return $this->channels()->whereHas('actorStates', function (Builder $query) use ($user): void {
            $query->where('actorable_type', $user->getMorphClass())
                ->where('actorable_id', $user->getKey());
        })->exists();
    }

    public function getFlowTypeAttribute(): ?string
    {
        return data_get($this->payload, 'flow_type');
    }

    public function getTitleAttribute(): ?string
    {
        return data_get($this->payload, 'title');
    }

    public function getDescriptionAttribute(): ?string
    {
        return data_get($this->payload, 'description');
    }

    public function setFlowTypeAttribute(?string $value): void
    {
        $this->putPayloadValue('flow_type', $value);
    }

    public function setTitleAttribute(?string $value): void
    {
        $this->putPayloadValue('title', $value);
    }

    public function setDescriptionAttribute(?string $value): void
    {
        $this->putPayloadValue('description', $value);
    }
}
