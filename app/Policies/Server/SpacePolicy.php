<?php

namespace App\Policies\Server;

use App\Models\Server\Space;
use App\Models\Server\User;

class SpacePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->canUseInteractiveTransport();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Space $space): bool
    {
        $serviceRequest = $space->requests()->first();

        if (! $serviceRequest) {
            return $space->hasActor($user);
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
    public function update(User $user, Space $space): bool
    {
        $serviceRequest = $space->requests()->first();

        if (! $serviceRequest) {
            return $space->hasActor($user);
        }

        return $serviceRequest->hasParticipant($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Space $space): bool
    {
        $serviceRequest = $space->requests()->first();

        if (! $serviceRequest) {
            return $space->hasActor($user);
        }

        return $serviceRequest->hasParticipant($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Space $space): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Space $space): bool
    {
        return false;
    }
}
