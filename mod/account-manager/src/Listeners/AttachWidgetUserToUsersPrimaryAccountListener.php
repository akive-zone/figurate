<?php

namespace Figurate\AccountManager\Listeners;

use App\Events\Server\Auth\SubjectAuthenticated;
use App\Features\Actions\Auth\ResolveWidgetUser;
use App\Models\Server\User;
use Figurate\AccountManager\Actions\AttachWidgetUserToUsersPrimaryAccount;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class AttachWidgetUserToUsersPrimaryAccountListener implements ShouldQueueAfterCommit
{
    public function __construct(
        protected AttachWidgetUserToUsersPrimaryAccount $attachWidgetUserToUsersPrimaryAccount,
        protected ResolveWidgetUser $resolveWidgetUser,
    ) {}

    public function handle(SubjectAuthenticated $event): void
    {
        if (! $event->user instanceof User || ! $event->user->isSubject()) {
            return;
        }

        $widgetUser = $this->resolveWidgetUser->execute($event->widgetUserContext);

        if (! $widgetUser instanceof User || $widgetUser->id === $event->user->id) {
            return;
        }

        ($this->attachWidgetUserToUsersPrimaryAccount)($widgetUser, $event->user);
    }
}
