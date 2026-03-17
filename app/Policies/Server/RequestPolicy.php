<?php

namespace App\Policies\Server;

use App\Models\Server\Fulfillment\Request;
use App\Models\Server\User;

class RequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSystem() || $user->isGadget() || $user->canActAsHuman();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Request $request): bool
    {
        if ($user->type === 'system') {
            return true;
        }

        if ($request->hasUserActor($user)) {
            return true;
        }

        return $request->profiles()
            ->where('profiles.user_id', $user->id)
            ->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isGadget() || $user->canActAsHuman();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Request $request): bool
    {
        return $user->type === 'system' || $request->hasUserActor($user, Request::ActionAsker);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Request $request): bool
    {
        return $user->type === 'system' || $request->hasUserActor($user, Request::ActionAsker);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Request $request): bool
    {
        return $user->type === 'system';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Request $request): bool
    {
        return $user->type === 'system';
    }
}
