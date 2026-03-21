<?php

namespace App\Policies\Server;

use App\Models\Server\Channel;
use App\Models\Server\User;

class ChannelPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isRobot() || $user->isGadget() || $user->canActAsHuman();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Channel $channel): bool
    {
        $serviceRequest = $channel->requests()->first();

        if (! $serviceRequest) {
            return $channel->hasActor($user);
        }

        return $serviceRequest->hasParticipant($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Channel $channel): bool
    {
        $serviceRequest = $channel->requests()->first();

        if (! $serviceRequest) {
            return $channel->hasActor($user);
        }

        return $serviceRequest->hasParticipant($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Channel $channel): bool
    {
        $serviceRequest = $channel->requests()->first();

        if (! $serviceRequest) {
            return $channel->hasActor($user);
        }

        return $serviceRequest->hasParticipant($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Channel $channel): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Channel $channel): bool
    {
        return false;
    }
}
