<?php

namespace App\Policies\Server;

use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;

class ThreadPolicy
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
    public function view(User $user, Thread $thread): bool
    {
        if ($thread->hasActor($user)) {
            return true;
        }

        $threadable = $thread->threadable;

        if ($threadable instanceof Space) {
            return $threadable->hasActor($user);
        }

        return false;
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
    public function update(User $user, Thread $thread): bool
    {
        if ($thread->hasActor($user)) {
            return true;
        }

        $threadable = $thread->threadable;

        if ($threadable instanceof Space) {
            return $threadable->hasActor($user);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Thread $thread): bool
    {
        return $this->update($user, $thread);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Thread $thread): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Thread $thread): bool
    {
        return false;
    }
}
