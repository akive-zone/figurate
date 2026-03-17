<?php

namespace App\Policies\Server;

use App\Models\Server\Fulfillment\Dispute;
use App\Models\Server\User;

class DisputePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSystem() || $user->canActAsHuman();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Dispute $dispute): bool
    {
        if ($user->type === 'system') {
            return true;
        }

        if ($dispute->opened_by === $user->id) {
            return true;
        }

        if ($dispute->order?->buyer_id === $user->id) {
            return true;
        }

        return $dispute->order?->sellerProfile?->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->canActAsHuman();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Dispute $dispute): bool
    {
        return $user->type === 'system' || $dispute->opened_by === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Dispute $dispute): bool
    {
        return $user->type === 'system';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Dispute $dispute): bool
    {
        return $user->type === 'system';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Dispute $dispute): bool
    {
        return $user->type === 'system';
    }
}
