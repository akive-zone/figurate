<?php

use App\Http\Controllers\Native\Signal\ChannelController as NativeSignalChannelController;
use App\Http\Controllers\Server\Auth\SocialiteController;
use App\Http\Controllers\Server\Web\Signal\ChannelController as ServerSignalChannelController;
use Illuminate\Support\Facades\Route;

if (\app_is_native_runtime()) {
    Route::get('/', function () {
        return view('native.launcher');
    })->name('launcher');

    Route::prefix('signal')
        ->name('signal.')
        ->group(function () {
            Route::get('/', [NativeSignalChannelController::class, 'index'])->name('index');
            Route::get('/channels/new', [NativeSignalChannelController::class, 'create'])->name('chat.create');
            Route::get('/channels/{channel}', [NativeSignalChannelController::class, 'show'])->name('chat.show');
        });

    Route::fallback(function () {
        return redirect()->to('/signal');
    });
} else {
    Route::prefix('auth')
        ->name('auth.')
        ->group(function () {
            Route::get('{provider}/redirect', [SocialiteController::class, 'redirect'])
                ->name('redirect');
            Route::get('{provider}/callback', [SocialiteController::class, 'callback'])
                ->name('callback');
        });

    Route::name('signal.')
        ->group(function () {
            Route::get('/', [ServerSignalChannelController::class, 'index'])->name('index');
            Route::redirect('/channels', '/');
            Route::get('/channels/new', [ServerSignalChannelController::class, 'create'])->name('chat.create');
            Route::get('/channels/{channel}', [ServerSignalChannelController::class, 'show'])->name('chat.show');
        });

    Route::fallback(function () {
        return redirect()->to('/');
    });
}
