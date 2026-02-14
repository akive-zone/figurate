<?php

use App\Http\Controllers\Server\Api\ChatController;
use App\Http\Controllers\Server\Api\RequestController;
use App\Http\Controllers\Server\Auth\ApiTokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [ApiTokenController::class, 'register']);
    Route::post('login', [ApiTokenController::class, 'login']);
    Route::post('logout', [ApiTokenController::class, 'logout'])
        ->middleware(['auth:sanctum']);
});

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::post('/request', [RequestController::class, 'store']);
    Route::post('/chat', [ChatController::class, 'store']);
});
