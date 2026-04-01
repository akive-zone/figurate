<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\PasskeyController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\GraphEdgeController;
use App\Http\Controllers\Api\Mcp\ServerController as McpServerController;
use App\Http\Controllers\Api\RobotUserController;
use App\Http\Controllers\Api\SpaceController;
use App\Http\Controllers\Api\SpacePostController;
use App\Http\Controllers\Api\SpaceThreadController;
use App\Http\Controllers\Api\ThreadController;
use App\Http\Controllers\Api\ThreadPostTurnsController;
use App\Http\Middleware\EnsureTransportUser;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;

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
        ->middleware(['auth:sanctum,passport', EnsureTransportUser::class.':subject']);
});

Route::prefix('graph')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::get('/edges', [GraphEdgeController::class, 'index'])->name('api.graph.edges.index');
    Route::post('/edges', [GraphEdgeController::class, 'store'])->name('api.graph.edges.store');
});

Route::prefix('form')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::post('/', [FormController::class, 'store'])->name('api.form.store');
});

Route::prefix('spaces')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::get('/', [SpaceController::class, 'index'])->name('api.spaces.index');
    Route::get('/{space}/posts', [SpacePostController::class, 'index'])->name('api.spaces.posts.index');
    Route::get('/{space}/threads', [SpaceThreadController::class, 'index'])->name('api.spaces.threads.index');
    Route::post('/{space}/threads', [SpaceThreadController::class, 'store'])->name('api.spaces.threads.store');
});

Route::prefix('threads')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::get('/{thread}', [ThreadController::class, 'show'])->name('api.threads.show');
    Route::get('/{thread}/posts/{message}/turns', ThreadPostTurnsController::class)->name('api.threads.posts.turns.show');
});

Route::prefix('channels')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::get('/', [McpServerController::class, 'index'])->name('api.context-servers.index');
    Route::post('/', [McpServerController::class, 'store'])->name('api.context-servers.store');
    Route::patch('/{channel}', [McpServerController::class, 'update'])->name('api.context-servers.update');
    Route::delete('/{channel}', [McpServerController::class, 'destroy'])->name('api.context-servers.destroy');
});
