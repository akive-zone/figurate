<?php

namespace App\Providers\Server;

use App\Listeners\Server\Auth\MarkDeviceSessionAsPasskeyVerified;
use App\Listeners\Server\Auth\MergeDeviceUsersAfterPasskeyAuthentication;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelPasskeys\Events\PasskeyUsedToAuthenticateEvent;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(PasskeyUsedToAuthenticateEvent::class, MergeDeviceUsersAfterPasskeyAuthentication::class);
        Event::listen(PasskeyUsedToAuthenticateEvent::class, MarkDeviceSessionAsPasskeyVerified::class);
    }
}
