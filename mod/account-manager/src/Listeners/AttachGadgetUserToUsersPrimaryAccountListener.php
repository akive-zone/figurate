<?php

namespace Figurate\AccountManager\Listeners;

use App\Http\Middleware\ResolveCurrentGadgetUser;
use App\Models\Server\User;
use Figurate\AccountManager\Actions\AttachGadgetUserToUsersPrimaryAccount;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class AttachGadgetUserToUsersPrimaryAccountListener
{
    public function __construct(protected AttachGadgetUserToUsersPrimaryAccount $attachGadgetUserToUsersPrimaryAccount) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User || ! $event->user->isSubject()) {
            return;
        }

        $request = $this->currentRequest();
        if (! $request instanceof Request) {
            return;
        }

        $gadgetUser = ResolveCurrentGadgetUser::resolvedUser($request);

        if (! $gadgetUser instanceof User || $gadgetUser->id === $event->user->id) {
            return;
        }

        ($this->attachGadgetUserToUsersPrimaryAccount)($gadgetUser, $event->user);
    }

    protected function currentRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        return $request instanceof Request ? $request : null;
    }
}
