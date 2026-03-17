<?php

namespace Figurate\FulfillmentManager\Policies\Server;

use App\Models\Server\User;
use Figurate\FulfillmentManager\Models\Quote;

class QuotePolicy
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
    public function view(User $user, Quote $quote): bool
    {
        if ($user->type === 'system') {
            return true;
        }

        if ($quote->profile?->user_id === $user->id) {
            return true;
        }

        return $quote->request?->hasUserActor($user) ?? false;
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
    public function update(User $user, Quote $quote): bool
    {
        if ($user->type === 'system') {
            return true;
        }

        if ($quote->profile?->user_id === $user->id) {
            return true;
        }

        return $quote->request?->hasUserActor($user) ?? false;
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
