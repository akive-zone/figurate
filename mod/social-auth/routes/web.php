<?php

use Figurate\SocialAuth\Http\Controllers\SocialiteController;
use Illuminate\Support\Facades\Route;

if (runtime() === 'server') {
    Route::prefix('auth')
        ->name('auth.')
        ->group(function (): void {
            Route::get('{provider}/redirect', [SocialiteController::class, 'redirect'])
                ->name('redirect');
            Route::get('{provider}/callback', [SocialiteController::class, 'callback'])
                ->name('callback');
        });
}
