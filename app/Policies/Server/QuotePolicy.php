<?php

namespace App\Policies\Server;

use App\Models\Server\Quote;
use App\Models\Server\User;

class QuotePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->type, ['system', 'person', 'device'], true);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Quote $quote): bool
    {
        if ($user->type === 'system') {
            return true;
        }

        if ($quote->profile?->user_id === $user->id) {
            return true;
        }

        return $quote->request?->requester_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->type === 'person';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Quote $quote): bool
    {
        if ($user->type === 'system') {
            return true;
        }

        if ($quote->profile?->user_id === $user->id) {
            return true;
        }

        return $quote->request?->requester_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Quote $quote): bool
    {
        return $user->type === 'system' || $quote->profile?->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Quote $quote): bool
    {
        return $user->type === 'system';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Quote $quote): bool
    {
        return $user->type === 'system';
    }
}
