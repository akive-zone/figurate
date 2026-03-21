<?php

use Figurate\MobileNative\Http\Controllers\Web\ChannelController;
use Illuminate\Support\Facades\Route;

if (runtime() !== 'mobile') {
    return;
}

Route::get('/', function () {
    return view('mobile-native::launcher');
})->name('launcher');

Route::prefix('chat')
    ->name('chat.')
    ->group(function (): void {
        Route::get('/', [ChannelController::class, 'index'])->name('index');
        Route::get('/create', [ChannelController::class, 'create'])->name('create');
        Route::get('/c/{channel}', [ChannelController::class, 'show'])->name('show');
        Route::get('/c/{channel}/t/{thread}', [ChannelController::class, 'showThread'])->name('thread');
    });

Route::fallback(function () {
    return redirect()->to('/chat');
});
