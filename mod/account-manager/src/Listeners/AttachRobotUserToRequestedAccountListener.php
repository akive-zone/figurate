<?php

namespace Figurate\AccountManager\Listeners;

use App\Models\Server\User;
use Figurate\AccountManager\Actions\AttachRobotUserToRequestedAccount;
use Figurate\Auth\Events\RobotProvisioned;

class AttachRobotUserToRequestedAccountListener
{
    public function __construct(protected AttachRobotUserToRequestedAccount $attachRobotUserToRequestedAccount) {}

    public function handle(RobotProvisioned $event): void
    {
        if (
            ! $event->creator instanceof User
            || ! $event->robot instanceof User
            || $event->requestedAccountUuid === null
            || trim($event->requestedAccountUuid) === ''
        ) {
            return;
        }

        ($this->attachRobotUserToRequestedAccount)(
            $event->robot,
            $event->creator,
            $event->requestedAccountUuid,
        );
    }
}
