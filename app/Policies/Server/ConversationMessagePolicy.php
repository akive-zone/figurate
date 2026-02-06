<?php

namespace App\Policies\Server;

use App\Models\Server\ConversationMessage;
use App\Models\Server\User;

class ConversationMessagePolicy
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
    public function view(User $user, ConversationMessage $conversationMessage): bool
    {
        if ($user->type === 'system') {
            return true;
        }

        if ($conversationMessage->sender_id === $user->id) {
            return true;
        }

        $conversation = $conversationMessage->conversation;

        if (! $conversation) {
            return false;
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
    public function update(User $user, ConversationMessage $conversationMessage): bool
    {
        return $user->type === 'system' || $conversationMessage->sender_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ConversationMessage $conversationMessage): bool
    {
        return $user->type === 'system' || $conversationMessage->sender_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ConversationMessage $conversationMessage): bool
    {
        return $user->type === 'system';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ConversationMessage $conversationMessage): bool
    {
        return $user->type === 'system';
    }
}
