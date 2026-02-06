<?php

namespace App\Policies\Server;

use App\Models\Server\Conversation;
use App\Models\Server\User;

class ConversationPolicy
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
    public function view(User $user, Conversation $conversation): bool
    {
        if ($user->type === 'system') {
            return true;
        }

        if ($conversation->requester_id === $user->id) {
            return true;
        }

        return $conversation->profile?->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->type, ['system', 'person', 'device'], true);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Conversation $conversation): bool
    {
        if ($user->type === 'system') {
            return true;
        }

        if ($conversation->requester_id === $user->id) {
            return true;
        }

        return $conversation->profile?->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Conversation $conversation): bool
    {
        if ($user->type === 'system') {
            return true;
        }

        if ($conversation->requester_id === $user->id) {
            return true;
        }

        return $conversation->profile?->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Conversation $conversation): bool
    {
        return $user->type === 'system';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Conversation $conversation): bool
    {
        return $user->type === 'system';
    }
}
