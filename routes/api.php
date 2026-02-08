<?php

use App\Http\Controllers\Server\Api\ChatController;
use App\Http\Controllers\Server\Api\OrderController;
use App\Http\Controllers\Server\Api\RequestController;
use App\Http\Controllers\Server\Auth\ApiTokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [ApiTokenController::class, 'register']);
    Route::post('login', [ApiTokenController::class, 'login']);
    Route::post('logout', [ApiTokenController::class, 'logout'])
        ->middleware(['auth:sanctum']);
});

Route::middleware(['web'])->group(function (): void {
    Route::post('/request', [RequestController::class, 'store']);
    Route::prefix('chat/{channel}')->group(function (): void {
        Route::post('/threads', [ChatController::class, 'storeThread']);
        Route::post('/threads/{thread}/messages', [ChatController::class, 'storeMessage']);
        Route::post('/threads/{thread}/prompt', [ChatController::class, 'promptThread']);
    });
    Route::post('/order/channels/{channel}/quotes/{quote}/accept', [OrderController::class, 'acceptQuote']);
});
