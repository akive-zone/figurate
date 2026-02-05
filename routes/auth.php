<?php

use App\Auth\Controllers\SocialiteController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')
    ->name('auth.')
    ->group(function () {
        Route::get('{provider}/redirect', [SocialiteController::class, 'redirect'])
            ->name('redirect');
        Route::get('{provider}/callback', [SocialiteController::class, 'callback'])
            ->name('callback');
    });
