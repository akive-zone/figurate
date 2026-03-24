<?php

namespace App\Support\Passkeys;

use App\Models\Server\User;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction as BaseFindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Models\Passkey;

class FindPasskeyToAuthenticateAction extends BaseFindPasskeyToAuthenticateAction
{
    public function execute(string $publicKeyCredentialJson, string $passkeyOptionsJson): ?Passkey
    {
        $passkey = $this->resolvePasskey($publicKeyCredentialJson, $passkeyOptionsJson);

        if (! $passkey) {
            return null;
        }

        $authenticatable = $passkey->authenticatable;
        if ($authenticatable instanceof User && $authenticatable->status !== 'active') {
            activity('auth')
                ->performedOn($authenticatable)
                ->event($this->deniedEventForStatus($authenticatable->status))
                ->withProperties([
                    'passkey_id' => $passkey->id,
                    'user_id' => $authenticatable->id,
                    'status' => $authenticatable->status,
                ])
                ->log($this->deniedMessageForStatus($authenticatable->status));

            return null;
        }

        return $passkey;
    }

    protected function resolvePasskey(string $publicKeyCredentialJson, string $passkeyOptionsJson): ?Passkey
    {
        return parent::execute($publicKeyCredentialJson, $passkeyOptionsJson);
    }

    protected function deniedEventForStatus(?string $status): string
    {
        if ($status === 'merged') {
            return 'auth.passkey_login_denied_merged_user';
        }

        return 'auth.passkey_login_denied_inactive_user';
    }

    protected function deniedMessageForStatus(?string $status): string
    {
        if ($status === 'merged') {
            return 'Passkey authentication denied for merged user.';
        }

        return 'Passkey authentication denied for inactive user.';
    }
}
