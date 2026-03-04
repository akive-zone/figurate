<?php

use App\Http\Controllers\Server\Api\A2a\StreamController as A2aStreamController;
use App\Http\Controllers\Server\Api\ApiTokenController;
use App\Http\Controllers\Server\Api\ChatController;
use App\Http\Controllers\Server\Api\ChatPostController;
use App\Http\Controllers\Server\Api\ChatThreadController;
use App\Http\Controllers\Server\Api\ContextServerController;
use App\Http\Middleware\EnsureA2aEnabled;
use App\Http\Middleware\EnsureA2aRpcAbility;
use App\Http\Middleware\EnsureDeviceUser;
use App\Http\Middleware\NormalizeA2aRpcMethodNames;
use App\Http\Procedures\A2aProcedure;
use App\Http\Procedures\A2aTasksProcedure;
use App\Http\Procedures\A2aTasksPushNotificationConfigProcedure;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [ApiTokenController::class, 'register']);
    Route::post('login', [ApiTokenController::class, 'login']);
    Route::post('logout', [ApiTokenController::class, 'logout'])
        ->middleware(['auth:sanctum']);
});

Route::post('broadcasting/auth', [BroadcastController::class, 'authenticate'])
    ->middleware([EnsureDeviceUser::class, 'auth:sanctum']);

Route::prefix('chats')->middleware([EnsureDeviceUser::class, 'auth:sanctum'])->group(function (): void {
    Route::post('/', [ChatController::class, 'store'])->name('api.chats.store');
    Route::get('/', [ChatController::class, 'index'])->name('api.chats.index');
    Route::get('/{chat}', [ChatController::class, 'show'])->name('api.chats.show');
    Route::get('/{chat}/messages/{message}/turns', [ChatController::class, 'showMessageTurns'])->name('api.chats.message-turns');
    Route::get('/{chat}/threads', [ChatThreadController::class, 'index'])->name('api.chats.threads');
    Route::post('/{chat}/threads', [ChatThreadController::class, 'store'])->name('api.chats.threads.store');
    Route::get('/{chat}/posts', [ChatPostController::class, 'index'])->name('api.chats.posts');
});

Route::prefix('context-servers')->middleware([EnsureDeviceUser::class, 'auth:sanctum'])->group(function (): void {
    Route::get('/', [ContextServerController::class, 'index'])->name('api.context-servers.index');
    Route::post('/', [ContextServerController::class, 'store'])->name('api.context-servers.store');
    Route::patch('/{contextServer}', [ContextServerController::class, 'update'])->name('api.context-servers.update');
    Route::delete('/{contextServer}', [ContextServerController::class, 'destroy'])->name('api.context-servers.destroy');
});

Route::prefix('a2a')->group(function (): void {
    Route::rpc('/rpc', [A2aProcedure::class, A2aTasksProcedure::class, A2aTasksPushNotificationConfigProcedure::class], '/')
        ->middleware([EnsureA2aEnabled::class, NormalizeA2aRpcMethodNames::class, 'auth:sanctum', EnsureA2aRpcAbility::class])
        ->name('api.a2a.rpc');
    Route::post('/stream', A2aStreamController::class)
        ->middleware([EnsureA2aEnabled::class, NormalizeA2aRpcMethodNames::class, 'auth:sanctum', EnsureA2aRpcAbility::class])
        ->name('api.a2a.stream');
    Route::webhooks('webhooks/push', 'a2a_push');
});
