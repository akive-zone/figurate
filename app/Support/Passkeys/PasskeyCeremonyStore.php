<?php

namespace App\Support\Passkeys;

use App\Models\Server\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PasskeyCeremonyStore
{
    public function storeRegistrationOptions(User $user, string $options): string
    {
        $ceremonyId = (string) Str::ulid();

        Cache::put(
            $this->cacheKey('registration', $ceremonyId),
            [
                'user_id' => $user->getKey(),
                'options' => $options,
            ],
            now()->addMinutes(10),
        );

        return $ceremonyId;
    }

    /**
     * @return array{user_id:int,options:string}|null
     */
    public function consumeRegistrationOptions(string $ceremonyId): ?array
    {
        $payload = Cache::pull($this->cacheKey('registration', $ceremonyId));

        if (! is_array($payload)) {
            return null;
        }

        $userId = (int) ($payload['user_id'] ?? 0);
        $options = $payload['options'] ?? null;

        if ($userId <= 0 || ! is_string($options) || $options === '') {
            return null;
        }

        return [
            'user_id' => $userId,
            'options' => $options,
        ];
    }

    protected function cacheKey(string $type, string $ceremonyId): string
    {
        return "passkeys:{$type}:{$ceremonyId}";
    }
}
