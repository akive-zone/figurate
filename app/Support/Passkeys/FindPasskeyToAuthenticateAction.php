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
            return null;
        }

        return $passkey;
    }
}
