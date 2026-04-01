<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\SpaceFactory;
use Figurate\FulfillmentManager\Models\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Space extends Model
{
    /** @use HasFactory<SpaceFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'status',
    ];

    public function actorStates(): HasMany
    {
        return $this->hasMany(SpaceActorState::class);
    }

    public function threads(): MorphMany
    {
        return $this->morphMany(Thread::class, 'threadable');
    }

    public function relations(): HasMany
    {
        return $this->hasMany(SpaceRelation::class);
    }

    public function attachRelation(
        EloquentModel $model,
        string $type = SpaceRelation::TypeRelatedTo,
        ?string $purpose = null,
    ): SpaceRelation {
        return $this->relations()->updateOrCreate(
            [
                'relationable_type' => $model->getMorphClass(),
                'relationable_id' => $model->getKey(),
                'type' => $type,
            ],
            [
                'purpose' => $purpose,
            ],
        );
    }

    public function requests(): MorphToMany
    {
        return $this->morphedByMany(
            Request::class,
            'relationable',
            'space_relations',
            'space_id',
            'relationable_id'
        )->withTimestamps();
    }

    public function posts(): MorphMany
    {
        return $this->morphMany(Post::class, 'postable');
    }

    public function stores(): MorphToMany
    {
        return $this->morphToMany(Store::class, 'storeable', 'storeables', 'storeable_id', 'store_id')
            ->withPivot(['scope', 'created_by', 'meta'])
            ->withTimestamps();
    }

    public function channelRelations(): MorphMany
    {
        return $this->morphMany(ChannelRelation::class, 'relationable');
    }

    public function contextServers(): MorphToMany
    {
        return $this->morphToMany(Channel::class, 'relationable', 'channel_relations', 'relationable_id', 'channel_id')
            ->wherePivot('kind', ChannelRelation::KindLink)
            ->where('driver', Channel::DriverMcp)
            ->withPivot(['kind', 'status', 'direction', 'data', 'meta'])
            ->withTimestamps();
    }

    public function hasActor(User $user): bool
    {
        return $this->actorStates()
            ->whereMorphedTo('actor', $user)
            ->exists();
    }

    public function primaryRequest(): ?Request
    {
        return $this->requests()->latest('id')->first();
    }

    public function primaryRequestPost(): ?Post
    {
        $postMorphClass = (new Post)->getMorphClass();

        return Post::query()
            ->whereIn('id', function ($query) use ($postMorphClass): void {
                $query->from('space_relations')
                    ->select('relationable_id')
                    ->where('space_id', $this->getKey())
                    ->whereIn('relationable_type', [$postMorphClass, Post::class])
                    ->where('type', 'request');
            })
            ->where('type', 'like', 'request.%')
            ->latest('id')
            ->first();
    }

    /**
     * @return Collection<int, Thread>
     */
    public function conversationThreads(): Collection
    {
        $threadIds = $this->conversationThreadIds();

        if ($threadIds->isEmpty()) {
            return collect();
        }

        return Thread::query()
            ->whereIn('id', $threadIds->all())
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param  class-string<EloquentModel>  $modelClass
     */
    public function relatedQuery(string $modelClass, ?string $type = null): Builder
    {
        $instance = new $modelClass;

        return $modelClass::query()
            ->whereIn($instance->getQualifiedKeyName(), function ($query) use ($instance, $type): void {
                $query->from('space_relations')
                    ->select('relationable_id')
                    ->where('space_id', $this->getKey())
                    ->where('relationable_type', $instance->getMorphClass());

                if ($type !== null) {
                    $query->where('type', $type);
                }
            });
    }

    /**
     * @param  class-string<EloquentModel>  $modelClass
     */
    public function relatedOne(string $modelClass, ?string $type = null): ?EloquentModel
    {
        return $this->relatedQuery($modelClass, $type)->first();
    }

    /**
     * @return Collection<int, SpaceRelation>
     */
    public function inboundSpaceRelations(?string $type = null): Collection
    {
        $query = SpaceRelation::query()
            ->whereMorphedTo('relationable', $this);

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, ThreadRelation>
     */
    public function inboundThreadRelations(?string $type = null): Collection
    {
        $query = ThreadRelation::query()
            ->whereMorphedTo('relationable', $this);

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, PostRelation>
     */
    public function inboundPostRelations(?string $role = null): Collection
    {
        $query = PostRelation::query()
            ->whereMorphedTo('relationable', $this);

        if ($role !== null) {
            $query->where('role', $role);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, Space>
     */
    public function relatedSpaces(?string $type = null): Collection
    {
        return $this->relatedQuery(self::class, $type)->get();
    }

    /**
     * @return Collection<int, Thread>
     */
    public function relatedThreads(?string $type = null): Collection
    {
        return $this->relatedQuery(Thread::class, $type)->get();
    }

    /**
     * @return Collection<int, Post>
     */
    public function relatedPosts(?string $type = null): Collection
    {
        return $this->relatedQuery(Post::class, $type)->get();
    }

    /**
     * @return Collection<int, int>
     */
    public function conversationThreadIds(int $depth = 2): Collection
    {
        $visitedSpaceIds = [];
        $visitedThreadIds = [];

        return $this->contextThreadIds($depth, $visitedSpaceIds, $visitedThreadIds);
    }

    /**
     * @param  array<int, bool>  $visitedSpaceIds
     * @param  array<int, bool>  $visitedThreadIds
     * @return Collection<int, int>
     */
    public function contextThreadIds(
        int $depth = 2,
        array &$visitedSpaceIds = [],
        array &$visitedThreadIds = [],
    ): Collection {
        $spaceId = (int) $this->getKey();

        if ($spaceId <= 0 || isset($visitedSpaceIds[$spaceId])) {
            return collect();
        }

        $visitedSpaceIds[$spaceId] = true;
        $threadIds = $this->directConversationThreadIds();

        if ($depth <= 0) {
            return $threadIds->unique()->values();
        }

        foreach ($this->relatedSpaces()->all() as $relatedSpace) {
            $threadIds = $threadIds->merge($relatedSpace->contextThreadIds($depth - 1, $visitedSpaceIds, $visitedThreadIds));
        }

        if ($threadIds->isEmpty()) {
            return collect();
        }

        $threads = Thread::query()
            ->whereIn('id', $threadIds->all())
            ->get();

        foreach ($threads as $thread) {
            $threadIds = $threadIds->merge($thread->contextThreadIds($depth - 1, $visitedSpaceIds, $visitedThreadIds));
        }

        return $threadIds
            ->filter(fn (mixed $value): bool => is_int($value) || (is_string($value) && ctype_digit($value)))
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    protected function directConversationThreadIds(): Collection
    {
        $directThreadIds = $this->threads()
            ->select('threads.id')
            ->pluck('threads.id');
        $threadRelationIds = ThreadRelation::query()
            ->whereMorphedTo('relationable', $this)
            ->pluck('thread_id');

        $relationRows = $this->relations()
            ->get(['relationable_type', 'relationable_id']);

        $threadMorphClass = (new Thread)->getMorphClass();
        $relatedThreadIds = $relationRows
            ->where('relationable_type', $threadMorphClass)
            ->pluck('relationable_id');

        $relationableGroups = $relationRows
            ->where('relationable_type', '!=', $threadMorphClass)
            ->groupBy('relationable_type')
            ->map(fn (Collection $rows): array => $rows->pluck('relationable_id')->filter()->unique()->values()->all())
            ->filter(fn (array $ids): bool => $ids !== []);

        $threadableThreadIds = collect();
        if ($relationableGroups->isNotEmpty()) {
            $threadableThreadIds = Thread::query()
                ->where(function ($query) use ($relationableGroups): void {
                    foreach ($relationableGroups as $relationableType => $relationableIds) {
                        $query->orWhere(function ($threadQuery) use ($relationableType, $relationableIds): void {
                            $threadQuery
                                ->where('threadable_type', $relationableType)
                                ->whereIn('threadable_id', $relationableIds);
                        });
                    }
                })
                ->pluck('id');
        }

        return collect()
            ->merge($directThreadIds)
            ->merge($threadRelationIds)
            ->merge($relatedThreadIds)
            ->merge($threadableThreadIds)
            ->filter(fn (mixed $value): bool => is_int($value) || (is_string($value) && ctype_digit($value)))
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, Post>
     */
    public function conversationPosts(): Collection
    {
        $threadIds = $this->conversationThreadIds();

        return Post::query()
            ->where(function ($query) use ($threadIds): void {
                $query->whereMorphedTo('postable', $this);

                if ($threadIds->isNotEmpty()) {
                    $query->orWhereHasMorph('postable', [Thread::class], function ($threadPostsQuery) use ($threadIds): void {
                        $threadPostsQuery->whereKey($threadIds->all());
                    });
                }
            })
            ->orderBy('occurred_at')
            ->orderBy('created_at')
            ->get();
    }

    public function latestConversationMessage(): ?Post
    {
        $threadIds = $this->conversationThreadIds();
        if ($threadIds->isEmpty()) {
            return null;
        }

        return Post::query()
            ->messageType()
            ->whereHasMorph('postable', [Thread::class], function ($query) use ($threadIds): void {
                $query->whereKey($threadIds->all());
            })
            ->latest('created_at')
            ->first();
    }
}
