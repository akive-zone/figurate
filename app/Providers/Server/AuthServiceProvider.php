<?php

namespace App\Providers\Server;

use App\Listeners\Server\Auth\MarkWidgetSessionAsPasskeyVerified;
use App\Listeners\Server\Auth\MergeWidgetUsersAfterPasskeyAuthentication;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelPasskeys\Events\PasskeyUsedToAuthenticateEvent;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(PasskeyUsedToAuthenticateEvent::class, MergeWidgetUsersAfterPasskeyAuthentication::class);
        Event::listen(PasskeyUsedToAuthenticateEvent::class, MarkWidgetSessionAsPasskeyVerified::class);
    }
}
