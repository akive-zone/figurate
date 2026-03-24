<?php

namespace App\Providers\Server;

use App\Listeners\Server\Auth\MarkGadgetSessionAsPasskeyVerified;
use App\Listeners\Server\Auth\MergeGadgetUsersAfterPasskeyAuthentication;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelPasskeys\Events\PasskeyUsedToAuthenticateEvent;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(PasskeyUsedToAuthenticateEvent::class, MergeGadgetUsersAfterPasskeyAuthentication::class);
        Event::listen(PasskeyUsedToAuthenticateEvent::class, MarkGadgetSessionAsPasskeyVerified::class);
    }
}
