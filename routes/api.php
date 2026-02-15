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
    Route::post('/chat', [ChatController::class, 'store']);
});
