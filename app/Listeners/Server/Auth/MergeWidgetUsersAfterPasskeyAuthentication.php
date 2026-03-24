<?php

namespace App\Listeners\Server\Auth;

use App\Contracts\Users\UserRepository;
use App\Features\Actions\Auth\MergeWidgetUserIntoSubject;
use App\Features\Actions\Auth\MergeWidgetUserIntoWidget;
use App\Models\Server\User;
use Spatie\LaravelPasskeys\Events\PasskeyUsedToAuthenticateEvent;

class MergeWidgetUsersAfterPasskeyAuthentication
{
    public function __construct(
        protected MergeWidgetUserIntoWidget $mergeWidgetUserIntoWidget,
        protected MergeWidgetUserIntoSubject $mergeWidgetUserIntoSubject,
        protected UserRepository $userRepository,
    ) {}

    public function handle(PasskeyUsedToAuthenticateEvent $event): void
    {
        $targetAuthenticatedUser = $event->passkey->authenticatable;
        if (! $targetAuthenticatedUser instanceof User) {
            return;
        }

        $sourceWidgetUserId = (int) $event->request->attributes->get('initial_widget_user_id', 0);
        if ($sourceWidgetUserId <= 0 || $sourceWidgetUserId === $targetAuthenticatedUser->id) {
            return;
        }

        $sourceWidgetUser = $this->userRepository->findById($sourceWidgetUserId);
        if (! $sourceWidgetUser instanceof User || ! $sourceWidgetUser->isWidget()) {
            return;
        }

        if ($targetAuthenticatedUser->isWidget()) {
            $this->mergeWidgetUserIntoWidget->execute($sourceWidgetUser, $targetAuthenticatedUser);

            activity('auth')
                ->causedBy($sourceWidgetUser)
                ->performedOn($targetAuthenticatedUser)
                ->event('auth.widget_user_merged_after_passkey_login')
                ->withProperties([
                    'source_user_id' => $sourceWidgetUser->id,
                    'target_user_id' => $targetAuthenticatedUser->id,
                    'passkey_id' => $event->passkey->id,
                ])
                ->log('Merged source widget user into passkey owner after passkey authentication.');

            return;
        }

        if ($targetAuthenticatedUser->isSubject()) {
            $this->mergeWidgetUserIntoSubject->execute($sourceWidgetUser, $targetAuthenticatedUser);

            activity('auth')
                ->causedBy($sourceWidgetUser)
                ->performedOn($targetAuthenticatedUser)
                ->event('auth.widget_user_merged_into_subject_after_passkey_login')
                ->withProperties([
                    'source_user_id' => $sourceWidgetUser->id,
                    'target_user_id' => $targetAuthenticatedUser->id,
                    'passkey_id' => $event->passkey->id,
                ])
                ->log('Merged source widget user into subject user after passkey authentication.');
        }
    }
}
