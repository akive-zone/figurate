<?php

namespace App\Support\Auth;

use App\Models\Server\User;

class ApiAbilityGate
{
    public function allows(User $user, string $ability): bool
    {
        $token = $user->currentAccessToken();
        $tokenName = is_object($token) && isset($token->name) ? (string) $token->name : '';

        return ! str_starts_with($tokenName, 'api:')
            || $user->tokenCan('*')
            || $user->tokenCan($ability);
    }
}
