<?php

use App\Http\Controllers\Native\Web\ChannelController as NativeChannelController;
use App\Http\Controllers\Server\Web\ChannelController as ServerChannelController;
use App\Http\Controllers\Server\Web\SocialiteController;
use App\Http\Middleware\EnsureDeviceUser;
use Illuminate\Support\Facades\Route;

if (\app_is_native_runtime()) {
    Route::get('/', function () {
        return view('native.launcher');
    })->name('launcher');

    Route::prefix('chat')
        ->name('chat.')
        ->group(function () {
            Route::get('/', [NativeChannelController::class, 'index'])->name('index');
            Route::get('/channels', [NativeChannelController::class, 'create'])->name('create');
            Route::get('/channels/{channel}', [NativeChannelController::class, 'show'])->name('show');
            Route::get('/channels/{channel}/threads/{thread}', [NativeChannelController::class, 'showThread'])->name('thread');
        });

    Route::fallback(function () {
        return redirect()->to('/chat');
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
        ->name('chat.')
        ->group(function () {
            Route::get('/', [ServerChannelController::class, 'index'])->name('index');
            Route::get('/channels', [ServerChannelController::class, 'create'])->name('create');
            Route::get('/channels/{channel}', [ServerChannelController::class, 'show'])->name('show');
            Route::get('/channels/{channel}/threads/{thread}', [ServerChannelController::class, 'showThread'])->name('thread');
        });
}
