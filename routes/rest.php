<?php

use App\Http\Controllers\Api\Auth\CurrentUserController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\PasskeyController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\ChannelAddressController;
use App\Http\Controllers\Api\ChannelConnectionController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\ChannelRouteController;
use App\Http\Controllers\Api\ChannelSkillMediaController;
use App\Http\Controllers\Api\CredentialController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\FormTurnsController;
use App\Http\Controllers\Api\GraphEdgeController;
use App\Http\Controllers\Api\GraphNodeController;
use App\Http\Controllers\Api\OpenApiController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PostNodeController;
use App\Http\Controllers\Api\RobotUserController;
use App\Http\Controllers\Api\SpaceController;
use App\Http\Controllers\Api\SpaceNodeController;
use App\Http\Controllers\Api\SpaceThreadController;
use App\Http\Controllers\Api\ThreadController;
use App\Http\Controllers\Api\ThreadNodeController;
use App\Http\Controllers\Api\ThreadPostController;
use App\Http\Middleware\EnsureTransportUser;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;

Route::get('openapi.json', OpenApiController::class)->name('api.openapi');

Route::prefix('auth')->group(function (): void {
    Route::post('register', RegisterController::class)->name('api.auth.register');
    Route::post('login', LoginController::class)->name('api.auth.login');
    Route::post('logout', LogoutController::class)
        ->middleware(['auth:sanctum,passport']);
    Route::post('broadcasting', [BroadcastController::class, 'authenticate'])
        ->middleware(['auth:sanctum,passport']);

    Route::get('user', [CurrentUserController::class, 'show'])
        ->middleware(['auth:sanctum,passport'])
        ->name('api.auth.user.show');
    Route::patch('user', [CurrentUserController::class, 'update'])
        ->middleware(['auth:sanctum,passport'])
        ->name('api.auth.user.update');

    Route::prefix('passkeys')
        ->name('api.passkeys.')
        ->group(function (): void {
            Route::get('/', [PasskeyController::class, 'index'])
                ->middleware(['auth:sanctum,passport'])
                ->name('index');
            Route::post('/options', [PasskeyController::class, 'generateRegisterOptions'])
                ->name('register-options');
            Route::post('/', [PasskeyController::class, 'store'])
                ->name('store');
            Route::delete('/{passkey}', [PasskeyController::class, 'destroy'])
                ->middleware(['auth:sanctum,passport'])
                ->name('destroy');
        });

    Route::prefix('credentials')
        ->middleware(['auth:sanctum,passport'])
        ->group(function (): void {
            Route::get('/', [CredentialController::class, 'index'])->name('api.auth.credentials.index');
            Route::post('/', [CredentialController::class, 'store'])->name('api.auth.credentials.store');
            Route::delete('/{credential}', [CredentialController::class, 'destroy'])->name('api.auth.credentials.destroy');
        });
});

Route::prefix('form')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::post('/', [FormController::class, 'store'])
        ->middleware('api.ability:forms:submit')
        ->name('api.form.store');

    Route::get('/edges', [GraphEdgeController::class, 'index'])
        ->middleware('api.ability:edges:read')
        ->name('api.form.edges.index');
    Route::post('/edges', [GraphEdgeController::class, 'store'])
        ->middleware(['api.ability:edges:write', 'api.idempotent'])
        ->name('api.form.edges.store');
    Route::patch('/edges/{edge}', [GraphEdgeController::class, 'update'])
        ->middleware('api.ability:edges:write')
        ->name('api.form.edges.update');
    Route::delete('/edges/{edge}', [GraphEdgeController::class, 'destroy'])
        ->middleware('api.ability:edges:write')
        ->name('api.form.edges.destroy');
    Route::post('/nodes', [GraphNodeController::class, 'store'])
        ->middleware(['api.ability:nodes:write', 'api.idempotent'])
        ->name('api.form.nodes.store');
    Route::get('/nodes/{type}/{node}', [GraphNodeController::class, 'show'])
        ->middleware('api.ability:nodes:read')
        ->name('api.form.nodes.show');
    Route::patch('/nodes/{type}/{node}', [GraphNodeController::class, 'update'])
        ->middleware('api.ability:nodes:write')
        ->name('api.form.nodes.update');
    Route::delete('/nodes/{type}/{node}', [GraphNodeController::class, 'destroy'])
        ->middleware('api.ability:nodes:write')
        ->name('api.form.nodes.destroy');
    Route::get('/{invocation}/turns', FormTurnsController::class)
        ->middleware('api.ability:invocations:read')
        ->name('api.form.turns.index');
});

Route::middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::get('/edges', [GraphEdgeController::class, 'index'])
        ->middleware('api.ability:edges:read')
        ->name('api.edges.index');
    Route::post('/edges', [GraphEdgeController::class, 'store'])
        ->middleware(['api.ability:edges:write', 'api.idempotent'])
        ->name('api.edges.store');
    Route::patch('/edges/{edge}', [GraphEdgeController::class, 'update'])
        ->middleware('api.ability:edges:write')
        ->name('api.edges.update');
    Route::delete('/edges/{edge}', [GraphEdgeController::class, 'destroy'])
        ->middleware('api.ability:edges:write')
        ->name('api.edges.destroy');

    Route::post('/nodes', [GraphNodeController::class, 'store'])
        ->middleware(['api.ability:nodes:write', 'api.idempotent'])
        ->name('api.nodes.store');
    Route::get('/nodes/{type}/{node}', [GraphNodeController::class, 'show'])
        ->middleware('api.ability:nodes:read')
        ->name('api.nodes.show');
    Route::patch('/nodes/{type}/{node}', [GraphNodeController::class, 'update'])
        ->middleware('api.ability:nodes:write')
        ->name('api.nodes.update');
    Route::delete('/nodes/{type}/{node}', [GraphNodeController::class, 'destroy'])
        ->middleware('api.ability:nodes:write')
        ->name('api.nodes.destroy');
});

Route::prefix('users')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::post('/', [RobotUserController::class, 'store'])
        ->middleware(['auth:sanctum,passport', EnsureTransportUser::class.':subject'])
        ->name('api.users.store');
});

Route::prefix('channels')->middleware(['auth:sanctum,passport', 'api.ability:channels:manage'])->group(function (): void {
    Route::get('/', [ChannelController::class, 'index'])->name('api.channels.index');
    Route::post('/', [ChannelController::class, 'store'])->name('api.channels.store');
    Route::patch('/{channel}', [ChannelController::class, 'update'])->name('api.channels.update');
    Route::delete('/{channel}', [ChannelController::class, 'destroy'])->name('api.channels.destroy');
    Route::post('/{channel}/skills', [ChannelSkillMediaController::class, 'storeForChannel'])->name('api.channels.skills.store');
    Route::get('/{channel}/connections', [ChannelConnectionController::class, 'index'])->name('api.channels.connections.index');
    Route::post('/{channel}/connections', [ChannelConnectionController::class, 'store'])->name('api.channels.connections.store');
    Route::patch('/{channel}/connections/{connection}', [ChannelConnectionController::class, 'update'])->name('api.channels.connections.update');
    Route::delete('/{channel}/connections/{connection}', [ChannelConnectionController::class, 'destroy'])->name('api.channels.connections.destroy');
    Route::get('/{channel}/routes', [ChannelRouteController::class, 'index'])->name('api.channels.routes.index');
    Route::post('/{channel}/routes', [ChannelRouteController::class, 'store'])->name('api.channels.routes.store');
    Route::patch('/{channel}/routes/{route}', [ChannelRouteController::class, 'update'])->name('api.channels.routes.update');
    Route::delete('/{channel}/routes/{route}', [ChannelRouteController::class, 'destroy'])->name('api.channels.routes.destroy');
    Route::post('/{channel}/routes/{route}/skills', [ChannelSkillMediaController::class, 'storeForRoute'])->name('api.channels.routes.skills.store');
    Route::get('/{channel}/routes/{route}/addresses', [ChannelAddressController::class, 'index'])->name('api.channels.routes.addresses.index');
    Route::post('/{channel}/routes/{route}/addresses', [ChannelAddressController::class, 'store'])->name('api.channels.routes.addresses.store');
    Route::patch('/{channel}/routes/{route}/addresses/{address}', [ChannelAddressController::class, 'update'])->name('api.channels.routes.addresses.update');
    Route::delete('/{channel}/routes/{route}/addresses/{address}', [ChannelAddressController::class, 'destroy'])->name('api.channels.routes.addresses.destroy');
    Route::post('/{channel}/routes/{route}/addresses/{address}/skills', [ChannelSkillMediaController::class, 'storeForAddress'])->name('api.channels.routes.addresses.skills.store');
});

Route::prefix('spaces')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::get('/', [SpaceController::class, 'index'])->middleware('api.ability:nodes:read')->name('api.spaces.index');
    Route::post('/', [SpaceController::class, 'store'])->middleware(['api.ability:nodes:write', 'api.idempotent'])->name('api.spaces.store');
    Route::get('/{space}', [SpaceController::class, 'show'])->middleware('api.ability:nodes:read')->name('api.spaces.show');
    Route::get('/{space}/nodes', SpaceNodeController::class)->middleware('api.ability:nodes:read')->name('api.spaces.nodes.index');
    Route::get('/{space}/threads', [SpaceThreadController::class, 'index'])->middleware('api.ability:nodes:read')->name('api.spaces.threads.index');
    Route::post('/{space}/threads', [SpaceThreadController::class, 'store'])->middleware('api.ability:nodes:write')->name('api.spaces.threads.store');
});

Route::prefix('threads')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::get('/{thread}', [ThreadController::class, 'show'])->middleware('api.ability:nodes:read')->name('api.threads.show');
    Route::get('/{thread}/nodes', ThreadNodeController::class)->middleware('api.ability:nodes:read')->name('api.threads.nodes.index');
    Route::get('/{thread}/posts', [ThreadPostController::class, 'index'])->middleware('api.ability:nodes:read')->name('api.threads.posts.index');
    Route::post('/{thread}/posts', [ThreadPostController::class, 'store'])->middleware('api.ability:nodes:write')->name('api.threads.posts.store');
});

Route::prefix('posts')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::get('/{post}', [PostController::class, 'show'])->middleware('api.ability:nodes:read')->name('api.posts.show');
    Route::get('/{post}/nodes', PostNodeController::class)->middleware('api.ability:nodes:read')->name('api.posts.nodes.index');
});
