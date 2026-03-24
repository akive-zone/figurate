<?php

namespace App\Listeners\Server\Auth;

use App\Models\Server\User;
use Spatie\LaravelPasskeys\Events\PasskeyUsedToAuthenticateEvent;

class MarkGadgetSessionAsPasskeyVerified
{
    public function handle(PasskeyUsedToAuthenticateEvent $event): void
    {
        $authenticatable = $event->passkey->authenticatable;

        if (! $authenticatable instanceof User || ! $authenticatable->isGadget()) {
            return;
        }

        $event->request->session()->put('auth.gadget_passkey', [
            'user_id' => $authenticatable->id,
            'passkey_id' => $event->passkey->id,
            'authenticated_at' => now()->toIso8601String(),
        ]);

        activity('auth')
            ->performedOn($authenticatable)
            ->event('auth.gadget_passkey_verified')
            ->withProperties([
                'passkey_id' => $event->passkey->id,
                'user_id' => $authenticatable->id,
            ])
            ->log('Gadget user authenticated with passkey and session was marked as verified.');
    }
}
