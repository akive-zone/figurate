<?php

namespace Figurate\FulfillmentManager\Policies\Server;

use App\Models\Server\User;
use Figurate\FulfillmentManager\Models\ServiceCategory;

class ServiceCategoryPolicy
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
    public function view(User $user, ServiceCategory $serviceCategory): bool
    {
        return $user->isSystem() || $user->isGadget() || $user->canActAsHuman();
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
    public function update(User $user, ServiceCategory $serviceCategory): bool
    {
        return $user->type === 'system';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ServiceCategory $serviceCategory): bool
    {
        return $user->type === 'system';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ServiceCategory $serviceCategory): bool
    {
        return $user->type === 'system';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ServiceCategory $serviceCategory): bool
    {
        return $user->type === 'system';
    }
}
