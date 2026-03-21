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

    public function consumeRegistrationOptions(User $user, string $ceremonyId): ?string
    {
        $payload = Cache::pull($this->cacheKey('registration', $ceremonyId));

        if (! is_array($payload)) {
            return null;
        }

        if ((int) ($payload['user_id'] ?? 0) !== (int) $user->getKey()) {
            return null;
        }

        $options = $payload['options'] ?? null;

        return is_string($options) && $options !== '' ? $options : null;
    }

    protected function cacheKey(string $type, string $ceremonyId): string
    {
        return "passkeys:{$type}:{$ceremonyId}";
    }
}
