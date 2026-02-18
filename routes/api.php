<?php

use App\Http\Controllers\Server\Api\ApiTokenController;
use App\Http\Controllers\Server\Api\ChatController;
use App\Http\Middleware\EnsureDeviceUser;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [ApiTokenController::class, 'register']);
    Route::post('login', [ApiTokenController::class, 'login']);
    Route::post('logout', [ApiTokenController::class, 'logout'])
        ->middleware(['auth:sanctum']);
});

Route::middleware([EnsureDeviceUser::class, 'auth:sanctum'])->group(function (): void {
    Route::get('/chats', [ChatController::class, 'index'])->name('api.chats.index');
    Route::get('/chats/{thread}', [ChatController::class, 'show'])->name('api.chats.show');
    Route::post('/chats', [ChatController::class, 'store'])->name('api.chats.store');
});
