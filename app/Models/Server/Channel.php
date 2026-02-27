<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Server\Fulfillment\Request;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Channel extends Model
{
    /** @use HasFactory<\Database\Factories\ChannelFactory> */
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
        return $this->hasMany(ChannelActorState::class);
    }

    public function threads(): MorphMany
    {
        return $this->morphMany(Thread::class, 'threadable');
    }

    public function relations(): HasMany
    {
        return $this->hasMany(ChannelRelation::class);
    }

    public function requests(): MorphToMany
    {
        return $this->morphedByMany(
            Request::class,
            'relationable',
            'channel_relations',
            'channel_id',
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

    public function hasActor(User $user): bool
    {
        return $this->actorStates()
            ->where('actorable_type', $user->getMorphClass())
            ->where('actorable_id', $user->getKey())
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
                $query->from('channel_relations')
                    ->select('relationable_id')
                    ->where('channel_id', $this->getKey())
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
     * @return Collection<int, int>
     */
    public function conversationThreadIds(): Collection
    {
        $directThreadIds = $this->threads()
            ->select('threads.id')
            ->pluck('threads.id');
        $threadRelationIds = ThreadRelation::query()
            ->where('relationable_type', $this->getMorphClass())
            ->where('relationable_id', $this->getKey())
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
        $channelMorphClass = $this->getMorphClass();
        $threadMorphClass = (new Thread)->getMorphClass();

        return Post::query()
            ->where(function ($query) use ($channelMorphClass, $threadMorphClass, $threadIds): void {
                $query->where(function ($channelPostsQuery) use ($channelMorphClass): void {
                    $channelPostsQuery
                        ->where('postable_type', $channelMorphClass)
                        ->where('postable_id', $this->getKey());
                });

                if ($threadIds->isNotEmpty()) {
                    $query->orWhere(function ($threadPostsQuery) use ($threadMorphClass, $threadIds): void {
                        $threadPostsQuery
                            ->where('postable_type', $threadMorphClass)
                            ->whereIn('postable_id', $threadIds->all());
                    });
                }
            })
            ->orderBy('occurred_at')
            ->orderBy('created_at')
            ->get();
    }

    public function latestConversationMessage(): ?Message
    {
        $threadIds = $this->conversationThreadIds();
        if ($threadIds->isEmpty()) {
            return null;
        }

        return Message::query()
            ->where('messageable_type', (new Thread)->getMorphClass())
            ->whereIn('messageable_id', $threadIds->all())
            ->latest('created_at')
            ->first();
    }
}
