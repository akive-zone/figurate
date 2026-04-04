<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\ThreadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Thread extends Model
{
    /** @use HasFactory<ThreadFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    public const PurposeMain = 'main';

    public const PurposePlanning = 'planning';

    public const PurposeExecution = 'execution';

    public const PurposeBilling = 'billing';

    public const PurposeDispute = 'dispute';

    public const PurposeSupport = 'support';

    public const PurposeSystem = 'system';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'threadable_type',
        'threadable_id',
        'purpose',
        'title',
        'phase',
        'status',
    ];

    public function threadable(): MorphTo
    {
        return $this->morphTo();
    }

    public function posts(): MorphMany
    {
        return $this->morphMany(Post::class, 'postable');
    }

    public function messages(): MorphMany
    {
        return $this->posts()->where('type', Post::TypeMessage);
    }

    public function actors(): HasMany
    {
        return $this->hasMany(ThreadActor::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ThreadEvent::class);
    }

    public function outboxes(): HasMany
    {
        return $this->hasMany(Outbox::class);
    }

    public function inboxes(): MorphMany
    {
        return $this->morphMany(Inbox::class, 'inboxable');
    }

    public function contextInboxes(): HasMany
    {
        return $this->hasMany(Inbox::class);
    }

    public function relations(): HasMany
    {
        return $this->hasMany(ThreadRelation::class);
    }

    public function attachRelation(
        EloquentModel $model,
        string $type = ThreadRelation::TypeRelatedTo,
        ?string $purpose = null,
    ): ThreadRelation {
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

    /**
     * @param  class-string<EloquentModel>  $modelClass
     */
    public function relatedQuery(string $modelClass, ?string $type = null): Builder
    {
        $instance = new $modelClass;

        return $modelClass::query()
            ->whereIn($instance->getQualifiedKeyName(), function ($query) use ($instance, $type): void {
                $query->from('thread_relations')
                    ->select('relationable_id')
                    ->where('thread_id', $this->getKey())
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
        return $this->relatedQuery(Space::class, $type)->get();
    }

    /**
     * @return Collection<int, Thread>
     */
    public function relatedThreads(?string $type = null): Collection
    {
        return $this->relatedQuery(self::class, $type)->get();
    }

    /**
     * @return Collection<int, Post>
     */
    public function relatedPosts(?string $type = null): Collection
    {
        return $this->relatedQuery(Post::class, $type)->get();
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
        $threadId = (int) $this->getKey();

        if ($threadId <= 0 || isset($visitedThreadIds[$threadId])) {
            return collect();
        }

        $visitedThreadIds[$threadId] = true;
        $threadIds = collect([$threadId]);

        if ($depth <= 0) {
            return $threadIds;
        }

        foreach ($this->relatedThreads()->all() as $relatedThread) {
            $threadIds = $threadIds->merge($relatedThread->contextThreadIds($depth - 1, $visitedSpaceIds, $visitedThreadIds));
        }

        foreach ($this->relatedSpaces()->all() as $relatedSpace) {
            $threadIds = $threadIds->merge($relatedSpace->contextThreadIds($depth - 1, $visitedSpaceIds, $visitedThreadIds));
        }

        return $threadIds->unique()->values();
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
        return $this->linkedChannels()
            ->where(function (Builder $query): void {
                $query
                    ->where('channels.driver', Channel::DriverMcp)
                    ->orWhere('channels.config->protocol', Channel::ProtocolMcp)
                    ->orWhere('channel_relations.config->protocol', Channel::ProtocolMcp);
            })
            ->withPivot([
                'id',
                'kind',
                'status',
                'direction',
                'config',
                'data',
                'meta',
            ]);
    }

    public function linkedChannels(): MorphToMany
    {
        return $this->morphToMany(Channel::class, 'relationable', 'channel_relations', 'relationable_id', 'channel_id')
            ->wherePivot('kind', ChannelRelation::KindLink)
            ->withPivot([
                'id',
                'kind',
                'status',
                'direction',
                'config',
                'data',
                'meta',
            ])
            ->withTimestamps();
    }

    public function channels(): MorphToMany
    {
        return $this->morphToMany(Channel::class, 'relationable', 'channel_relations', 'relationable_id', 'channel_id')
            ->wherePivot('kind', ChannelRelation::KindBind)
            ->withPivot([
                'id',
                'kind',
                'status',
                'direction',
                'config',
                'data',
                'meta',
            ])
            ->withTimestamps();
    }
}
