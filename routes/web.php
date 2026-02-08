<?php

use App\Http\Controllers\Server\Auth\SocialiteController;
use App\Http\Controllers\Signal\ChannelController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (config('app.context') === 'native' || config('nativephp-internal.running')) {
        return view('native.launcher');
    }

    return redirect()->to('/signal');
})->name('launcher');

Route::prefix('auth')
    ->name('auth.')
    ->group(function () {
        Route::get('{provider}/redirect', [SocialiteController::class, 'redirect'])
            ->name('redirect');
        Route::get('{provider}/callback', [SocialiteController::class, 'callback'])
            ->name('callback');
    });

Route::prefix('signal')
    ->name('signal.')
    ->group(function () {
        Route::get('/', [ChannelController::class, 'index'])->name('index');
        Route::get('/chat/{channel}', [ChannelController::class, 'show'])->name('chat.show');
    });

Route::fallback(function () {
    return redirect()->to('/signal');
});
