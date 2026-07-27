<?php

namespace App\Support\Channels;

use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ChannelLinkRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(
        Channel $channel,
        Space|Thread|Post $container,
        Space|Thread|Post $target,
        array $attributes = [],
        ?User $actor = null,
    ): Post {
        $link = $container->posts()->create([
            'type' => Post::TypeChannelLink,
            'tag' => $channel->uuid,
            'status' => (string) ($attributes['status'] ?? Post::StatusActive),
            'payload' => [
                'direction' => (string) ($attributes['direction'] ?? Channel::DirectionBidirectional),
                'config' => is_array($attributes['config'] ?? null) ? $attributes['config'] : [],
                'data' => is_array($attributes['data'] ?? null) ? $attributes['data'] : [],
            ],
            'meta' => is_array($attributes['meta'] ?? null) ? $attributes['meta'] : [],
        ]);

        $link->attachRelation($channel, Post::RelationRoleChannel);
        $link->attachRelation($target, Post::RelationRoleChannelLink);

        if ($actor instanceof User) {
            $link->attachRelation($actor, Post::RelationRoleSender);
        }

        return $link->refresh();
    }

    /**
     * @return Collection<int, Post>
     */
    public function forContext(Model $context): Collection
    {
        if ($context instanceof User) {
            $spaceIds = SpaceActorState::query()
                ->whereMorphedTo('actor', $context)
                ->where('status', SpaceActorState::StatusActive)
                ->pluck('space_id');

            if ($spaceIds->isEmpty()) {
                return collect();
            }

            return $this->linkQuery()
                ->whereHas('relations', function (Builder $query) use ($spaceIds): void {
                    $query
                        ->where('role', Post::RelationRoleChannelLink)
                        ->where('relationable_type', (new Space)->getMorphClass())
                        ->whereIn('relationable_id', $spaceIds->all());
                })
                ->get();
        }

        if (! $context instanceof Space && ! $context instanceof Thread && ! $context instanceof Post) {
            return collect();
        }

        return $this->linkQuery()
            ->whereHas('relations', function (Builder $query) use ($context): void {
                $query
                    ->where('role', Post::RelationRoleChannelLink)
                    ->where('relationable_type', $context->getMorphClass())
                    ->where('relationable_id', $context->getKey());
            })
            ->get();
    }

    /**
     * @return Collection<int, Post>
     */
    public function forChannel(Channel $channel): Collection
    {
        return $this->linkQuery()
            ->whereHas('relations', function (Builder $query) use ($channel): void {
                $query
                    ->where('role', Post::RelationRoleChannel)
                    ->where('relationable_type', $channel->getMorphClass())
                    ->where('relationable_id', $channel->getKey());
            })
            ->get();
    }

    public function channel(Post $link): ?Channel
    {
        $channel = $link->relatedOne(Channel::class, Post::RelationRoleChannel);

        return $channel instanceof Channel ? $channel : null;
    }

    /**
     * @return Collection<int, Space|Thread|Post>
     */
    public function targets(Post $link): Collection
    {
        return $link->relations()
            ->with('relationable')
            ->where('role', Post::RelationRoleChannelLink)
            ->get()
            ->pluck('relationable')
            ->filter(fn (mixed $target): bool => $target instanceof Space || $target instanceof Thread || $target instanceof Post)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function config(Post $link): array
    {
        $config = data_get($link->payload, 'config');

        return is_array($config) ? $config : [];
    }

    public function direction(Post $link): string
    {
        $direction = data_get($link->payload, 'direction');

        return is_string($direction) && trim($direction) !== ''
            ? strtolower(trim($direction))
            : Channel::DirectionBidirectional;
    }

    protected function linkQuery(): Builder
    {
        return Post::query()
            ->where('type', Post::TypeChannelLink)
            ->where('status', Post::StatusActive)
            ->latest('id');
    }
}
