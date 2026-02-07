<?php

namespace App\Policies\Server;

use App\Models\Server\Channel;
use App\Models\Server\Message;
use App\Models\Server\Request;
use App\Models\Server\User;

class MessagePolicy
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
    public function view(User $user, Message $message): bool
    {
        if ($user->type === 'system') {
            return true;
        }

        if ($message->sender_id === $user->id) {
            return true;
        }

        $messageable = $message->messageable;

        if ($messageable instanceof Request) {
            if ($messageable->requester_id === $user->id) {
                return true;
            }

            return $messageable->profile?->user_id === $user->id;
        }

        if ($messageable instanceof Channel) {
            if ($messageable->requester_id === $user->id) {
                return true;
            }

            return $messageable->profile?->user_id === $user->id;
        }

        return false;
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
    public function update(User $user, Message $message): bool
    {
        return $user->type === 'system' || $message->sender_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Message $message): bool
    {
        return $user->type === 'system' || $message->sender_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Message $message): bool
    {
        return $user->type === 'system';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Message $message): bool
    {
        return $user->type === 'system';
    }
}
