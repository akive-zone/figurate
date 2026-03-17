<?php

namespace Figurate\FulfillmentManager\Policies\Server;

use App\Models\Server\User;
use Figurate\FulfillmentManager\Models\Payment;

class PaymentPolicy
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
    public function view(User $user, Payment $payment): bool
    {
        if ($user->type === 'system') {
            return true;
        }

        if ($payment->order?->buyer_id === $user->id) {
            return true;
        }

        return $payment->order?->sellerProfile?->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->type === 'system';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Payment $payment): bool
    {
        return $user->type === 'system';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Payment $payment): bool
    {
        return $user->type === 'system';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Payment $payment): bool
    {
        return $user->type === 'system';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Payment $payment): bool
    {
        return $user->type === 'system';
    }
}
