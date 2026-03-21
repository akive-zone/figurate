<?php

namespace App\Listeners\Server\Auth;

use App\Contracts\Users\UserRepository;
use App\Features\Actions\Auth\MergeDeviceUserIntoDeviceUser;
use App\Features\Actions\Auth\MergeDeviceUserIntoPerson;
use App\Models\Server\User;
use Spatie\LaravelPasskeys\Events\PasskeyUsedToAuthenticateEvent;

class MergeDeviceUsersAfterPasskeyAuthentication
{
    public function __construct(
        protected MergeDeviceUserIntoDeviceUser $mergeDeviceUserIntoDeviceUser,
        protected MergeDeviceUserIntoPerson $mergeDeviceUserIntoPerson,
        protected UserRepository $userRepository,
    ) {}

    public function handle(PasskeyUsedToAuthenticateEvent $event): void
    {
        $targetAuthenticatedUser = $event->passkey->authenticatable;
        if (! $targetAuthenticatedUser instanceof User) {
            return;
        }

        $sourceDeviceUserId = (int) $event->request->attributes->get('initial_device_user_id', 0);
        if ($sourceDeviceUserId <= 0 || $sourceDeviceUserId === $targetAuthenticatedUser->id) {
            return;
        }

        $sourceDeviceUser = $this->userRepository->findById($sourceDeviceUserId);
        if (! $sourceDeviceUser instanceof User || ! $sourceDeviceUser->isGadget()) {
            return;
        }

        if ($targetAuthenticatedUser->isGadget()) {
            $this->mergeDeviceUserIntoDeviceUser->execute($sourceDeviceUser, $targetAuthenticatedUser);

            activity('auth')
                ->causedBy($sourceDeviceUser)
                ->performedOn($targetAuthenticatedUser)
                ->event('auth.device_user_merged_after_passkey_login')
                ->withProperties([
                    'source_user_id' => $sourceDeviceUser->id,
                    'target_user_id' => $targetAuthenticatedUser->id,
                    'passkey_id' => $event->passkey->id,
                ])
                ->log('Merged source gadget user into passkey owner after passkey authentication.');

            return;
        }

        if ($targetAuthenticatedUser->isSubject()) {
            $this->mergeDeviceUserIntoPerson->execute($sourceDeviceUser, $targetAuthenticatedUser);

            activity('auth')
                ->causedBy($sourceDeviceUser)
                ->performedOn($targetAuthenticatedUser)
                ->event('auth.device_user_merged_into_subject_after_passkey_login')
                ->withProperties([
                    'source_user_id' => $sourceDeviceUser->id,
                    'target_user_id' => $targetAuthenticatedUser->id,
                    'passkey_id' => $event->passkey->id,
                ])
                ->log('Merged source gadget user into subject user after passkey authentication.');
        }
    }
}
