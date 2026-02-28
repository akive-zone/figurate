<?php

namespace App\Listeners\Server\Auth;

use App\Models\Server\User;
use Spatie\LaravelPasskeys\Events\PasskeyUsedToAuthenticateEvent;

class MarkDeviceSessionAsPasskeyVerified
{
    public function handle(PasskeyUsedToAuthenticateEvent $event): void
    {
        $authenticatable = $event->passkey->authenticatable;

        if (! $authenticatable instanceof User || $authenticatable->type !== 'device') {
            return;
        }

        $event->request->session()->put('auth.device_passkey', [
            'user_id' => $authenticatable->id,
            'passkey_id' => $event->passkey->id,
            'authenticated_at' => now()->toIso8601String(),
        ]);
    }
}
