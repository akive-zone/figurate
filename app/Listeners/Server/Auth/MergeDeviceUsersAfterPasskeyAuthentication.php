<?php

namespace App\Listeners\Server\Auth;

use App\Actions\Server\Auth\MergeDeviceUserIntoDeviceUser;
use App\Models\Server\User;
use Spatie\LaravelPasskeys\Events\PasskeyUsedToAuthenticateEvent;

class MergeDeviceUsersAfterPasskeyAuthentication
{
    public function __construct(protected MergeDeviceUserIntoDeviceUser $mergeDeviceUserIntoDeviceUser) {}

    public function handle(PasskeyUsedToAuthenticateEvent $event): void
    {
        $targetAuthenticatedUser = $event->passkey->authenticatable;
        if (! $targetAuthenticatedUser instanceof User || $targetAuthenticatedUser->type !== 'device') {
            return;
        }

        $sourceDeviceUserId = (int) $event->request->attributes->get('initial_device_user_id', 0);
        if ($sourceDeviceUserId <= 0 || $sourceDeviceUserId === $targetAuthenticatedUser->id) {
            return;
        }

        $sourceDeviceUser = User::query()->find($sourceDeviceUserId);
        if (! $sourceDeviceUser instanceof User || $sourceDeviceUser->type !== 'device') {
            return;
        }

        ($this->mergeDeviceUserIntoDeviceUser)($sourceDeviceUser, $targetAuthenticatedUser);

        activity('auth')
            ->causedBy($sourceDeviceUser)
            ->performedOn($targetAuthenticatedUser)
            ->event('auth.device_user_merged_after_passkey_login')
            ->withProperties([
                'source_user_id' => $sourceDeviceUser->id,
                'target_user_id' => $targetAuthenticatedUser->id,
                'passkey_id' => $event->passkey->id,
            ])
            ->log('Merged source device user into passkey owner after passkey authentication.');
    }
}
