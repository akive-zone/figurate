<?php

namespace App\Support\Channels;

use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class ChannelAccess
{
    public function __construct(protected ChannelLinkRepository $channelLinks) {}

    public function canManage(User $actor, Channel $channel): bool
    {
        foreach ($this->channelLinks->forChannel($channel) as $link) {
            foreach ($this->channelLinks->targets($link) as $target) {
                if ($this->ownsModel($actor, $target)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isAttachedTo(User $actor, Channel $channel, Model $owner): bool
    {
        if (! $this->ownsModel($actor, $owner)) {
            return false;
        }

        foreach ($this->channelLinks->forChannel($channel) as $link) {
            if ($this->channelLinks->targets($link)->contains(
                fn (Model $target): bool => $target->is($owner),
            )) {
                return true;
            }
        }

        return $channel->routes()
            ->whereHas('addresses', function ($query) use ($owner): void {
                $query
                    ->where('addressable_type', $owner->getMorphClass())
                    ->where('addressable_id', $owner->getKey());
            })
            ->exists();
    }

    protected function ownsModel(User $actor, mixed $model): bool
    {
        if (! $model instanceof Model) {
            return false;
        }

        return match (true) {
            $model instanceof User => (int) $model->id === (int) $actor->id,
            $model instanceof Space => Gate::forUser($actor)->check('view', $model),
            $model instanceof Thread => Gate::forUser($actor)->check('view', $model),
            $model instanceof Post => Gate::forUser($actor)->check('view', $model),
            default => false,
        };
    }
}
