<?php

namespace App\Policies\Server;

use App\Models\Server\Message;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Figurate\FulfillmentManager\Models\Request;

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
        return $user->canUseInteractiveTransport();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Message $message): bool
    {
        if ($this->isSender($user, $message)) {
            return true;
        }

        $messageable = $message->messageable;

        if ($messageable instanceof Request) {
            if ($messageable->hasUserActor($user)) {
                return true;
            }

            return false;
        }

        if ($messageable instanceof Space) {
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

            if ($threadable instanceof Space) {
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
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Message $message): bool
    {
        return $this->isSender($user, $message);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Message $message): bool
    {
        return $this->isSender($user, $message);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Message $message): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Message $message): bool
    {
        return false;
    }
}
