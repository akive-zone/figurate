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

        ($this->mergeDeviceUserIntoDeviceUser)($sourceDeviceUser, $targetAuthenticatedUser);
    }
}
