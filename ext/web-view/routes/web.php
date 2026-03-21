<?php

use App\Http\Middleware\EnsureDeviceUser;
use Figurate\WebView\Http\Controllers\Web\ChannelController;
use Illuminate\Support\Facades\Route;

if (runtime() === 'server') {
    Route::middleware(EnsureDeviceUser::class)
        ->name('chat.')
        ->group(function () {
            Route::get('/', [ChannelController::class, 'index'])->name('index');
            Route::get('/create', [ChannelController::class, 'index'])->name('create');
            Route::get('/c/{channel}', [ChannelController::class, 'show'])->name('show');
            Route::get('/c/{channel}/t/{thread}', [ChannelController::class, 'showThread'])->name('thread');
        });
}
