<?php

use App\Http\Controllers\Server\Api\ChannelThreadController;
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
    Route::post('/chat/{channel}', [ChatController::class, 'store']);
    Route::post('/order/channels/{channel}/quotes/{quote}/accept', [OrderController::class, 'acceptQuote']);
    Route::post('/channels/{channel}/threads', [ChannelThreadController::class, 'store']);
    Route::post('/channels/{channel}/threads/{thread}/activate', [ChannelThreadController::class, 'activate']);
    Route::patch('/threads/{thread}', [ChannelThreadController::class, 'update']);
});
