<?php

namespace Figurate\AccountManager\Listeners;

use App\Models\Server\User;
use Figurate\AccountManager\Actions\EnsurePrimaryAccountForUser;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;

class EnsurePrimaryAccountForUserListener
{
    public function __construct(protected EnsurePrimaryAccountForUser $ensurePrimaryAccountForUser) {}

    public function handle(Login|Registered $event): void
    {
        if (! $event->user instanceof User || ! $event->user->isSubject()) {
            return;
        }

        ($this->ensurePrimaryAccountForUser)($event->user);
    }
}
