<?php

use App\Http\Controllers\Native\Signal\ChannelController as NativeSignalChannelController;
use App\Http\Controllers\Server\Auth\SocialiteController;
use App\Http\Controllers\Server\Web\Signal\ChannelController as ServerSignalChannelController;
use App\Http\Middleware\EnsureDeviceUser;
use Illuminate\Support\Facades\Route;

if (\app_is_native_runtime()) {
    Route::get('/', function () {
        return view('native.launcher');
    })->name('launcher');

    Route::prefix('signal')
        ->name('signal.')
        ->group(function () {
            Route::get('/', [NativeSignalChannelController::class, 'index'])->name('index');
            Route::get('/channels', [NativeSignalChannelController::class, 'create'])->name('chat.create');
            Route::get('/channels/{channel}', [NativeSignalChannelController::class, 'show'])->name('chat.show');
            Route::get('/channels/{channel}/threads/{thread}', [NativeSignalChannelController::class, 'showThread'])->name('chat.thread');
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

    Route::middleware(EnsureDeviceUser::class)
        ->name('signal.')
        ->group(function () {
            Route::get('/', [ServerSignalChannelController::class, 'index'])->name('index');
            Route::get('/channels', [ServerSignalChannelController::class, 'create'])->name('chat.create');
            Route::get('/channels/{channel}', [ServerSignalChannelController::class, 'show'])->name('chat.show');
            Route::get('/channels/{channel}/threads/{thread}', [ServerSignalChannelController::class, 'showThread'])->name('chat.thread');
        });
}
