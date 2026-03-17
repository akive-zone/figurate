<?php

namespace App\Policies\Server;

use App\Models\Server\Fulfillment\Rating;
use App\Models\Server\User;

class RatingPolicy
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
    public function view(User $user, Rating $rating): bool
    {
        if ($user->type === 'system') {
            return true;
        }

        if ($rating->rater_id === $user->id) {
            return true;
        }

        return $rating->rated_id === $user->id;
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
    public function update(User $user, Rating $rating): bool
    {
        return $user->type === 'system' || $rating->rater_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Rating $rating): bool
    {
        return $user->type === 'system';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Rating $rating): bool
    {
        return $user->type === 'system';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Rating $rating): bool
    {
        return $user->type === 'system';
    }
}
