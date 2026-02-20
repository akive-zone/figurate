<?php

use App\Http\Controllers\Server\Api\ApiTokenController;
use App\Http\Controllers\Server\Api\ChatController;
use App\Http\Controllers\Server\Api\ChatPostController;
use App\Http\Controllers\Server\Api\ChatThreadController;
use App\Http\Middleware\EnsureDeviceUser;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [ApiTokenController::class, 'register']);
    Route::post('login', [ApiTokenController::class, 'login']);
    Route::post('logout', [ApiTokenController::class, 'logout'])
        ->middleware(['auth:sanctum']);
});

Route::prefix('chats')->middleware([EnsureDeviceUser::class, 'auth:sanctum'])->group(function (): void {
    Route::post('/', [ChatController::class, 'store'])->name('api.chats.store');
    Route::get('/', [ChatController::class, 'index'])->name('api.chats.index');
    Route::get('/{chat}', [ChatController::class, 'show'])->name('api.chats.show');
    Route::get('/{chat}/threads', [ChatThreadController::class, 'index'])->name('api.chats.threads');
    Route::get('/{chat}/posts', [ChatPostController::class, 'index'])->name('api.chats.posts');
});
