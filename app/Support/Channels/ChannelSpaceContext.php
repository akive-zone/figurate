<?php

namespace App\Support\Channels;

use App\Features\Actions\Chat\BootstrapConversationSpaceContext;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class ChannelSpaceContext
{
    public function __construct(
        protected BootstrapConversationSpaceContext $bootstrapConversationSpaceContext,
    ) {}

    public function resolve(
        User $actor,
        string $ownerType,
        Model $owner,
        ?string $spaceId = null,
    ): Space {
        if (is_string($spaceId) && trim($spaceId) !== '') {
            $space = Space::query()->where('uuid', trim($spaceId))->firstOrFail();
            Gate::forUser($actor)->authorize('view', $space);

            return $space;
        }

        if ($ownerType === 'space' && $owner instanceof Space) {
            return $owner;
        }

        if ($ownerType === 'thread' && $owner instanceof Thread) {
            $threadable = $owner->relationLoaded('threadable') ? $owner->threadable : $owner->threadable()->first();

            if ($threadable instanceof Space) {
                return $threadable;
            }
        }

        if ($ownerType === 'post' && $owner instanceof Post) {
            $space = $this->spaceForPost($owner);

            if ($space instanceof Space) {
                return $space;
            }
        }

        return $this->bootstrapConversationSpaceContext->execute($actor);
    }

    protected function spaceForPost(Post $post): ?Space
    {
        $parent = $post->postable;

        return match (true) {
            $parent instanceof Space => $parent,
            $parent instanceof Thread => $parent->threadable instanceof Space ? $parent->threadable : null,
            $parent instanceof Post => $this->spaceForPost($parent),
            default => null,
        };
    }
}
