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

        activity('auth')
            ->performedOn($authenticatable)
            ->event('auth.device_passkey_verified')
            ->withProperties([
                'passkey_id' => $event->passkey->id,
                'user_id' => $authenticatable->id,
            ])
            ->log('Device user authenticated with passkey and session was marked as verified.');
    }
}
