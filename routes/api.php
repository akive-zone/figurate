<?php

use App\Http\Controllers\Api\A2a\StreamController as A2aStreamController;
use App\Http\Controllers\Api\Acp\SessionController as AcpSessionController;
use App\Http\Controllers\Api\Acp\TaskController as AcpTaskController;
use App\Http\Controllers\Api\AgentUserController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\PasskeyController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ChatPostController;
use App\Http\Controllers\Api\ChatThreadController;
use App\Http\Controllers\Api\Mcp\ServerController as McpServerController;
use App\Http\Middleware\EnsureA2aEnabled;
use App\Http\Middleware\EnsureA2aRpcAbility;
use App\Http\Middleware\EnsureDeviceUser;
use App\Http\Middleware\EnsureTokenAbility;
use App\Http\Middleware\EnsureTransportUser;
use App\Http\Middleware\NormalizeA2aRpcMethodNames;
use App\Http\Middleware\ResolveCurrentGadgetUser;
use App\Http\Procedures\A2aProcedure;
use App\Http\Procedures\A2aTasksProcedure;
use App\Http\Procedures\A2aTasksPushNotificationConfigProcedure;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', RegisterController::class);
    Route::post('login', LoginController::class)
        ->middleware([ResolveCurrentGadgetUser::class]);
    Route::post('logout', LogoutController::class)
        ->middleware(['auth:sanctum,passport']);

    Route::middleware(['auth:sanctum,passport'])
        ->prefix('passkeys')
        ->name('api.passkeys.')
        ->group(function (): void {
            Route::get('/', [PasskeyController::class, 'index'])->name('index');
            Route::post('/options/register', [PasskeyController::class, 'generateRegisterOptions'])->name('register-options');
            Route::post('/', [PasskeyController::class, 'store'])->name('store');
            Route::delete('/{passkey}', [PasskeyController::class, 'destroy'])->name('destroy');
        });
});

Route::post('broadcasting/auth', [BroadcastController::class, 'authenticate'])
    ->middleware([EnsureDeviceUser::class, 'auth:sanctum,passport']);

Route::post('agents', [AgentUserController::class, 'store'])
    ->middleware(['auth:sanctum,passport', EnsureTransportUser::class.':subject']);

Route::prefix('chats')->middleware([EnsureDeviceUser::class, 'auth:sanctum,passport'])->group(function (): void {
    Route::post('/', [ChatController::class, 'store'])->name('api.chats.store');
    Route::get('/', [ChatController::class, 'index'])->name('api.chats.index');
    Route::get('/{chat}', [ChatController::class, 'show'])->name('api.chats.show');
    Route::get('/{chat}/messages/{message}/turns', [ChatController::class, 'showMessageTurns'])->name('api.chats.message-turns');
    Route::get('/{chat}/threads', [ChatThreadController::class, 'index'])->name('api.chats.threads');
    Route::post('/{chat}/threads', [ChatThreadController::class, 'store'])->name('api.chats.threads.store');
    Route::get('/{chat}/posts', [ChatPostController::class, 'index'])->name('api.chats.posts');
});

Route::prefix('mcp')->middleware([EnsureDeviceUser::class, 'auth:sanctum,passport'])->group(function (): void {
    Route::get('/servers', [McpServerController::class, 'index'])->name('api.context-servers.index');
    Route::post('/servers', [McpServerController::class, 'store'])->name('api.context-servers.store');
    Route::patch('/servers/{server}', [McpServerController::class, 'update'])->name('api.context-servers.update');
    Route::delete('/servers/{server}', [McpServerController::class, 'destroy'])->name('api.context-servers.destroy');
});

Route::prefix('acp')->middleware(['auth:sanctum,passport', EnsureTransportUser::class, EnsureTokenAbility::class.':acp:use'])->group(function (): void {
    Route::get('/sessions', [AcpSessionController::class, 'index'])->name('api.acp.sessions.index');
    Route::post('/sessions', [AcpSessionController::class, 'store'])->name('api.acp.sessions.store');
    Route::get('/sessions/{session}', [AcpSessionController::class, 'show'])->name('api.acp.sessions.show');
    Route::post('/sessions/{session}/prompt', [AcpSessionController::class, 'prompt'])->name('api.acp.sessions.prompt');
    Route::get('/tasks/{task}', [AcpTaskController::class, 'show'])->name('api.acp.tasks.show');
    Route::post('/tasks/{task}/cancel', [AcpTaskController::class, 'cancel'])->name('api.acp.tasks.cancel');
});

Route::prefix('a2a')->group(function (): void {
    Route::rpc('/rpc', [A2aProcedure::class, A2aTasksProcedure::class, A2aTasksPushNotificationConfigProcedure::class], '/')
        ->middleware([EnsureA2aEnabled::class, NormalizeA2aRpcMethodNames::class, 'auth:sanctum,passport', EnsureTransportUser::class, EnsureA2aRpcAbility::class])
        ->name('api.a2a.rpc');
    Route::post('/stream', A2aStreamController::class)
        ->middleware([EnsureA2aEnabled::class, NormalizeA2aRpcMethodNames::class, 'auth:sanctum,passport', EnsureTransportUser::class, EnsureA2aRpcAbility::class])
        ->name('api.a2a.stream');
    Route::webhooks('webhooks/push', 'a2a_push');
});
