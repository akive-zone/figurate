<?php

namespace App\Models\Server;

use Laravel\Passport\Contracts\ScopeAuthorizable;
use Laravel\Passport\HasApiTokens;

class PassportUser extends User
{
    use HasApiTokens {
        currentAccessToken as protected passportCurrentAccessToken;
        tokenCan as protected passportTokenCan;
        tokenCant as protected passportTokenCant;
        withAccessToken as protected passportWithAccessToken;
    }

    public function withAccessToken($accessToken)
    {
        return $this->passportWithAccessToken(
            $accessToken instanceof ScopeAuthorizable ? $accessToken : null,
        );
    }

    public function currentAccessToken()
    {
        return $this->passportCurrentAccessToken();
    }

    public function tokenCan(string $ability)
    {
        return $this->passportTokenCan($ability);
    }

    public function tokenCant(string $ability)
    {
        return $this->passportTokenCant($ability);
    }
}
