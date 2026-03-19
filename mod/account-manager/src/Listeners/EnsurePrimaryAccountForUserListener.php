<?php

namespace Figurate\AccountManager\Listeners;

use App\Events\Accounts\EnsurePrimaryAccountForUserRequested;
use Figurate\AccountManager\Actions\EnsurePrimaryAccountForUser;

class EnsurePrimaryAccountForUserListener
{
    public function __construct(protected EnsurePrimaryAccountForUser $ensurePrimaryAccountForUser) {}

    public function handle(EnsurePrimaryAccountForUserRequested $event): void
    {
        ($this->ensurePrimaryAccountForUser)($event->user);
    }
}
