<?php

namespace Figurate\AccountManager\Contracts;

use App\Models\Server\User;

interface AccountContextFactory
{
    public function forUser(User $user): AccountContext;
}
