<?php

namespace App\Listeners\Server\Auth;

use App\Actions\Server\Auth\AttachGadgetUserToAccount;
use App\Actions\Server\Auth\MergeDeviceUserIntoDeviceUser;
use App\Actions\Server\Auth\MergeDeviceUserIntoPerson;
use App\Models\Server\User;
use Spatie\LaravelPasskeys\Events\PasskeyUsedToAuthenticateEvent;

class MergeDeviceUsersAfterPasskeyAuthentication
{
    public function __construct(
        protected AttachGadgetUserToAccount $attachGadgetUserToAccount,
        protected MergeDeviceUserIntoDeviceUser $mergeDeviceUserIntoDeviceUser,
        protected MergeDeviceUserIntoPerson $mergeDeviceUserIntoPerson,
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

        $sourceDeviceUser = User::query()->find($sourceDeviceUserId);
        if (! $sourceDeviceUser instanceof User || ! $sourceDeviceUser->isGadget()) {
            return;
        }

        if ($targetAuthenticatedUser->isGadget() && $targetAuthenticatedUser->hasAccount()) {
            $account = $targetAuthenticatedUser->primaryAccount();

            if ($account !== null) {
                ($this->attachGadgetUserToAccount)($sourceDeviceUser, $account);

                activity('auth')
                    ->causedBy($sourceDeviceUser)
                    ->performedOn($targetAuthenticatedUser)
                    ->event('auth.gadget_user_attached_to_account_after_passkey_login')
                    ->withProperties([
                        'source_user_id' => $sourceDeviceUser->id,
                        'target_user_id' => $targetAuthenticatedUser->id,
                        'account_id' => $account->id,
                        'passkey_id' => $event->passkey->id,
                    ])
                    ->log('Attached source gadget user to authenticated account after passkey authentication.');

                return;
            }
        }

        if ($targetAuthenticatedUser->isGadget()) {
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
                ->log('Merged source gadget user into passkey owner after passkey authentication.');

            return;
        }

        if ($targetAuthenticatedUser->isSubject()) {
            ($this->mergeDeviceUserIntoPerson)($sourceDeviceUser, $targetAuthenticatedUser);

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
