<?php

use App\Ai\Gateways\Mcp\Servers\FigurateServer;
use App\Http\Controllers\Api\A2a\StreamController as A2aStreamController;
use App\Http\Controllers\Api\Acp\SessionController as AcpSessionController;
use App\Http\Controllers\Api\Acp\TaskController as AcpTaskController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\PasskeyController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ConversationMessageTurnsController;
use App\Http\Controllers\Api\ConversationPostController;
use App\Http\Controllers\Api\ConversationThreadController;
use App\Http\Controllers\Api\Mcp\ServerController as McpServerController;
use App\Http\Controllers\Api\RobotUserController;
use App\Http\Middleware\EnsureA2aEnabled;
use App\Http\Middleware\EnsureA2aRpcAbility;
use App\Http\Middleware\EnsureTokenAbility;
use App\Http\Middleware\EnsureTransportUser;
use App\Http\Middleware\NormalizeA2aRpcMethodNames;
use App\Http\Procedures\A2aProcedure;
use App\Http\Procedures\A2aTasksProcedure;
use App\Http\Procedures\A2aTasksPushNotificationConfigProcedure;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::prefix('auth')->group(function (): void {
    Route::post('register', RegisterController::class);
    Route::post('login', LoginController::class);
    Route::post('logout', LogoutController::class)
        ->middleware(['auth:sanctum,passport']);
    Route::post('broadcasting', [BroadcastController::class, 'authenticate'])
        ->middleware(['auth:sanctum,passport']);

    Route::prefix('passkeys')
        ->name('api.passkeys.')
        ->group(function (): void {
            Route::get('/', [PasskeyController::class, 'index'])
                ->middleware(['auth:sanctum,passport'])
                ->name('index');
            Route::post('/options/register', [PasskeyController::class, 'generateRegisterOptions'])
                ->name('register-options');
            Route::post('/', [PasskeyController::class, 'store'])
                ->name('store');
            Route::delete('/{passkey}', [PasskeyController::class, 'destroy'])
                ->middleware(['auth:sanctum,passport'])
                ->name('destroy');
        });

    Route::post('robots', [RobotUserController::class, 'store'])
        ->middleware(['auth:sanctum,passport', EnsureTransportUser::class . ':subject']);
});

Route::prefix('conversations')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::post('/', [ConversationController::class, 'store'])->name('api.conversations.store');
    Route::get('/', [ConversationController::class, 'index'])->name('api.conversations.index');
    Route::get('/{conversation}', [ConversationController::class, 'show'])->name('api.conversations.show');
    Route::get('/{conversation}/messages/{message}/turns', ConversationMessageTurnsController::class)->name('api.conversations.message-turns.show');
    Route::get('/{conversation}/threads', [ConversationThreadController::class, 'index'])->name('api.conversations.threads.index');
    Route::post('/{conversation}/threads', [ConversationThreadController::class, 'store'])->name('api.conversations.threads.store');
    Route::get('/{conversation}/posts', [ConversationPostController::class, 'index'])->name('api.conversations.posts.index');
});

Route::prefix('mcp')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Mcp::web('/', FigurateServer::class)
        ->middleware([EnsureTransportUser::class, EnsureTokenAbility::class . ':mcp:use']);

    Route::get('/servers', [McpServerController::class, 'index'])->name('api.context-servers.index');
    Route::post('/servers', [McpServerController::class, 'store'])->name('api.context-servers.store');
    Route::patch('/servers/{server}', [McpServerController::class, 'update'])->name('api.context-servers.update');
    Route::delete('/servers/{server}', [McpServerController::class, 'destroy'])->name('api.context-servers.destroy');
});

Route::prefix('acp')->middleware(['auth:sanctum,passport', EnsureTransportUser::class, EnsureTokenAbility::class . ':acp:use'])->group(function (): void {
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
