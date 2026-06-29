<?php

namespace App\Support\Channels;

use App\Features\Actions\Conversation\BootstrapConversationSpaceContext;
use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
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
        Channel $channel,
        string $ownerType,
        Model $owner,
        ?string $spaceId = null,
    ): Space {
        if (is_string($spaceId) && trim($spaceId) !== '') {
            $space = Space::query()->where('uuid', trim($spaceId))->firstOrFail();
            Gate::forUser($actor)->authorize('view', $space);
            $this->attach($channel, $space);

            return $space;
        }

        if ($ownerType === 'space' && $owner instanceof Space) {
            $this->attach($channel, $owner);

            return $owner;
        }

        if ($ownerType === 'thread' && $owner instanceof Thread) {
            $threadable = $owner->relationLoaded('threadable') ? $owner->threadable : $owner->threadable()->first();

            if ($threadable instanceof Space) {
                $this->attach($channel, $threadable);

                return $threadable;
            }
        }

        $existingSpace = $channel->relations()
            ->where('relationable_type', (new Space)->getMorphClass())
            ->latest('id')
            ->first()?->relationable;

        if ($existingSpace instanceof Space) {
            return $existingSpace;
        }

        $space = $this->bootstrapConversationSpaceContext->execute($actor);
        $this->attach($channel, $space);

        return $space;
    }

    protected function attach(Channel $channel, Space $space): void
    {
        $channel->relations()->updateOrCreate(
            [
                'relationable_type' => $space->getMorphClass(),
                'relationable_id' => $space->getKey(),
                'kind' => ChannelRelation::KindLink,
            ],
            [
                'status' => Channel::StatusActive,
                'direction' => Channel::DirectionBidirectional,
                'config' => [],
                'data' => [],
                'meta' => [],
            ],
        );
    }
}
