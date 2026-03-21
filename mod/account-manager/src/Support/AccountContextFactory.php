<?php

namespace Figurate\AccountManager\Support;

use App\Models\Server\User;
use Figurate\AccountManager\Contracts\AccountContext as AccountContextContract;
use Figurate\AccountManager\Contracts\AccountContextFactory as AccountContextFactoryContract;

class AccountContextFactory implements AccountContextFactoryContract
{
    public function forUser(User $user): AccountContextContract
    {
        return new AccountContext($user);
    }
}
