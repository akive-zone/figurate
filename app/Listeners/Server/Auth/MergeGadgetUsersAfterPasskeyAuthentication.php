<?php

namespace App\Listeners\Server\Auth;

use App\Contracts\Users\UserRepository;
use App\Features\Actions\Auth\MergeGadgetUserIntoGadgetUser;
use App\Features\Actions\Auth\MergeGadgetUserIntoPerson;
use App\Models\Server\User;
use Spatie\LaravelPasskeys\Events\PasskeyUsedToAuthenticateEvent;

class MergeGadgetUsersAfterPasskeyAuthentication
{
    public function __construct(
        protected MergeGadgetUserIntoGadgetUser $mergeGadgetUserIntoGadgetUser,
        protected MergeGadgetUserIntoPerson $mergeGadgetUserIntoPerson,
        protected UserRepository $userRepository,
    ) {}

    public function handle(PasskeyUsedToAuthenticateEvent $event): void
    {
        $targetAuthenticatedUser = $event->passkey->authenticatable;
        if (! $targetAuthenticatedUser instanceof User) {
            return;
        }

        $sourceGadgetUserId = (int) $event->request->attributes->get('initial_gadget_user_id', 0);
        if ($sourceGadgetUserId <= 0 || $sourceGadgetUserId === $targetAuthenticatedUser->id) {
            return;
        }

        $sourceGadgetUser = $this->userRepository->findById($sourceGadgetUserId);
        if (! $sourceGadgetUser instanceof User || ! $sourceGadgetUser->isGadget()) {
            return;
        }

        if ($targetAuthenticatedUser->isGadget()) {
            $this->mergeGadgetUserIntoGadgetUser->execute($sourceGadgetUser, $targetAuthenticatedUser);

            activity('auth')
                ->causedBy($sourceGadgetUser)
                ->performedOn($targetAuthenticatedUser)
                ->event('auth.gadget_user_merged_after_passkey_login')
                ->withProperties([
                    'source_user_id' => $sourceGadgetUser->id,
                    'target_user_id' => $targetAuthenticatedUser->id,
                    'passkey_id' => $event->passkey->id,
                ])
                ->log('Merged source gadget user into passkey owner after passkey authentication.');

            return;
        }

        if ($targetAuthenticatedUser->isSubject()) {
            $this->mergeGadgetUserIntoPerson->execute($sourceGadgetUser, $targetAuthenticatedUser);

            activity('auth')
                ->causedBy($sourceGadgetUser)
                ->performedOn($targetAuthenticatedUser)
                ->event('auth.gadget_user_merged_into_subject_after_passkey_login')
                ->withProperties([
                    'source_user_id' => $sourceGadgetUser->id,
                    'target_user_id' => $targetAuthenticatedUser->id,
                    'passkey_id' => $event->passkey->id,
                ])
                ->log('Merged source gadget user into subject user after passkey authentication.');
        }
    }
}
