<?php

namespace App\Support\Passkeys;

use App\Models\Server\User;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction as BaseFindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Models\Passkey;

class FindPasskeyToAuthenticateAction extends BaseFindPasskeyToAuthenticateAction
{
    public function execute(string $publicKeyCredentialJson, string $passkeyOptionsJson): ?Passkey
    {
        $passkey = parent::execute($publicKeyCredentialJson, $passkeyOptionsJson);

        if (! $passkey) {
            return null;
        }

        $authenticatable = $passkey->authenticatable;
        if ($authenticatable instanceof User && $authenticatable->status === 'merged') {
            activity('auth')
                ->performedOn($authenticatable)
                ->event('auth.passkey_login_denied_merged_user')
                ->withProperties([
                    'passkey_id' => $passkey->id,
                    'user_id' => $authenticatable->id,
                ])
                ->log('Passkey authentication denied for merged user.');

            return null;
        }

        return $passkey;
    }
}
