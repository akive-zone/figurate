<?php

namespace App\Models\Server;

use DateTimeInterface;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;

class SanctumUser extends User
{
    use HasApiTokens;

    /**
     * @param  list<string>  $abilities
     */
    public function createToken(
        string $name,
        array $abilities = ['*'],
        ?DateTimeInterface $expiresAt = null,
    ): NewAccessToken {
        $plainTextToken = $this->generateTokenString();
        $token = PersonalAccessToken::query()->forceCreate([
            'tokenable_type' => self::class,
            'tokenable_id' => $this->getKey(),
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return new NewAccessToken($token, $token->getKey().'|'.$plainTextToken);
    }
}
