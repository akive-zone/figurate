<?php

namespace Figurate\AccountManager\Listeners;

use App\Events\Accounts\AttachGadgetUserToUsersPrimaryAccountRequested;
use Figurate\AccountManager\Actions\AttachGadgetUserToUsersPrimaryAccount;

class AttachGadgetUserToUsersPrimaryAccountListener
{
    public function __construct(protected AttachGadgetUserToUsersPrimaryAccount $attachGadgetUserToUsersPrimaryAccount) {}

    public function handle(AttachGadgetUserToUsersPrimaryAccountRequested $event): void
    {
        ($this->attachGadgetUserToUsersPrimaryAccount)($event->gadgetUser, $event->user);
    }
}
