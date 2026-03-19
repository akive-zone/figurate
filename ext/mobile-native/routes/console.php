<?php

use Figurate\MobileNative\Console\Commands\SetupNativeSystem;
use Illuminate\Support\Facades\Artisan;

Artisan::command('setup:native', function (): int {
    return app(SetupNativeSystem::class)->handle();
})->purpose('Copy .env.example and append NativePHP + API env variables');
