<?php

namespace App\Policies\Server;

use App\Models\Server\Channel;
use App\Models\Server\Fulfillment\Request;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\User;

class MessagePolicy
{
    protected function isSender(User $user, Message $message): bool
    {
        return $message->senderable_type === $user->getMorphClass()
            && (string) $message->senderable_id === (string) $user->getKey();
    }

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

        if ($this->isSender($user, $message)) {
            return true;
        }

        $messageable = $message->messageable;

        if ($messageable instanceof Request) {
            if ($messageable->hasUserActor($user)) {
                return true;
            }

            return $messageable->profiles()
                ->where('profiles.user_id', $user->id)
                ->exists();
        }

        if ($messageable instanceof Channel) {
            $serviceRequest = $messageable->requests()->first();

            if (! $serviceRequest) {
                return $messageable->hasActor($user);
            }

            return $serviceRequest->hasParticipant($user);
        }

        if ($messageable instanceof Thread) {
            $threadable = $messageable->threadable;

            if ($threadable instanceof Request) {
                return $threadable->hasParticipant($user);
            }

            if ($threadable instanceof Channel) {
                return $threadable->hasActor($user);
            }
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
        return $user->type === 'system' || $this->isSender($user, $message);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Message $message): bool
    {
        return $user->type === 'system' || $this->isSender($user, $message);
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
