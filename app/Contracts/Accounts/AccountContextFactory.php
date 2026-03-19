<?php

namespace App\Contracts\Accounts;

use App\Models\Server\User;

interface AccountContextFactory
{
    public function forUser(User $user): AccountContext;
}
