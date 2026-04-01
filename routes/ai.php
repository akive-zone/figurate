<?php

use App\Ai\Gateways\Mcp\Servers\FigurateServer;
use App\Http\Controllers\Api\A2a\AgentCardController;
use App\Http\Controllers\Api\A2a\StreamController as A2aStreamController;
use App\Http\Controllers\Api\Acp\SessionController as AcpSessionController;
use App\Http\Controllers\Api\Acp\TaskController as AcpTaskController;
use App\Http\Middleware\EnsureA2aEnabled;
use App\Http\Middleware\EnsureA2aRpcAbility;
use App\Http\Middleware\EnsureTokenAbility;
use App\Http\Middleware\EnsureTransportUser;
use App\Http\Middleware\NormalizeA2aRpcMethodNames;
use App\Http\Procedures\A2aProcedure;
use App\Http\Procedures\A2aTasksProcedure;
use App\Http\Procedures\A2aTasksPushNotificationConfigProcedure;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::get('/.well-known/agent-card', AgentCardController::class)->name('a2a.agent-card');

Mcp::oauthRoutes();

Route::prefix('mcp')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Mcp::web('/', FigurateServer::class)
        ->middleware([EnsureTransportUser::class, EnsureTokenAbility::class.':mcp:use']);
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
