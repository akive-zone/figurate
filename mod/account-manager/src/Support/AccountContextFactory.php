<?php

namespace Figurate\AccountManager\Support;

use App\Contracts\Accounts\AccountContext as AccountContextContract;
use App\Contracts\Accounts\AccountContextFactory as AccountContextFactoryContract;
use App\Models\Server\User;

class AccountContextFactory implements AccountContextFactoryContract
{
    public function forUser(User $user): AccountContextContract
    {
        return new AccountContext($user);
    }
}
