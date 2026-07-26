<?php

use App\Http\Controllers\Api\ApiCredentialController;
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

Route::prefix('auth')->group(function (): void {
    Route::post('register', RegisterController::class);
    Route::post('login', LoginController::class);
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
});

Route::prefix('form')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::post('/', [FormController::class, 'store'])->name('api.form.store');

    Route::get('/edges', [GraphEdgeController::class, 'index'])->name('api.form.edges.index');
    Route::post('/edges', [GraphEdgeController::class, 'store'])
        ->middleware('api.idempotent')
        ->name('api.form.edges.store');
    Route::patch('/edges/{edge}', [GraphEdgeController::class, 'update'])->name('api.form.edges.update');
    Route::delete('/edges/{edge}', [GraphEdgeController::class, 'destroy'])->name('api.form.edges.destroy');
    Route::post('/nodes', [GraphNodeController::class, 'store'])
        ->middleware('api.idempotent')
        ->name('api.form.nodes.store');
    Route::get('/nodes/{type}/{node}', [GraphNodeController::class, 'show'])->name('api.form.nodes.show');
    Route::patch('/nodes/{type}/{node}', [GraphNodeController::class, 'update'])->name('api.form.nodes.update');
    Route::delete('/nodes/{type}/{node}', [GraphNodeController::class, 'destroy'])->name('api.form.nodes.destroy');
    Route::get('/{invocation}/turns', FormTurnsController::class)->name('api.form.turns.index');
});

Route::prefix('users')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::post('/', [RobotUserController::class, 'store'])
        ->middleware(['auth:sanctum,passport', EnsureTransportUser::class.':subject'])
        ->name('api.users.store');
});

Route::prefix('channels')->middleware(['auth:sanctum,passport'])->group(function (): void {
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
    Route::get('/', [SpaceController::class, 'index'])->name('api.spaces.index');
    Route::post('/', [SpaceController::class, 'store'])->name('api.spaces.store');
    Route::get('/{space}', [SpaceController::class, 'show'])->name('api.spaces.show');
    Route::get('/{space}/nodes', SpaceNodeController::class)->name('api.spaces.nodes.index');
    Route::get('/{space}/threads', [SpaceThreadController::class, 'index'])->name('api.spaces.threads.index');
    Route::post('/{space}/threads', [SpaceThreadController::class, 'store'])->name('api.spaces.threads.store');
});

Route::prefix('threads')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::get('/{thread}', [ThreadController::class, 'show'])->name('api.threads.show');
    Route::get('/{thread}/nodes', ThreadNodeController::class)->name('api.threads.nodes.index');
    Route::get('/{thread}/posts', [ThreadPostController::class, 'index'])->name('api.threads.posts.index');
    Route::post('/{thread}/posts', [ThreadPostController::class, 'store'])->name('api.threads.posts.store');
});

Route::prefix('posts')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::get('/{post}', [PostController::class, 'show'])->name('api.posts.show');
    Route::get('/{post}/nodes', PostNodeController::class)->name('api.posts.nodes.index');
});

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/openapi.json', OpenApiController::class)->name('openapi');

    Route::prefix('auth')->group(function (): void {
        Route::post('/register', RegisterController::class)->name('auth.register');
        Route::post('/login', LoginController::class)->name('auth.login');
    });

    Route::prefix('credentials')
        ->middleware(['auth:sanctum,passport'])
        ->group(function (): void {
            Route::get('/', [ApiCredentialController::class, 'index'])->name('credentials.index');
            Route::post('/', [ApiCredentialController::class, 'store'])->name('credentials.store');
            Route::delete('/{credential}', [ApiCredentialController::class, 'destroy'])->name('credentials.destroy');
        });

    Route::middleware(['auth:sanctum,passport'])->group(function (): void {
        Route::post('/form', [FormController::class, 'store'])
            ->middleware('api.ability:forms:submit')
            ->name('form.store');
        Route::get('/form/{invocation}/turns', FormTurnsController::class)
            ->middleware('api.ability:invocations:read')
            ->name('form.turns.index');

        Route::get('/nodes/{type}/{node}', [GraphNodeController::class, 'show'])
            ->middleware('api.ability:nodes:read')
            ->name('nodes.show');
        Route::post('/nodes', [GraphNodeController::class, 'store'])
            ->middleware(['api.ability:nodes:write', 'api.idempotent'])
            ->name('nodes.store');
        Route::patch('/nodes/{type}/{node}', [GraphNodeController::class, 'update'])
            ->middleware('api.ability:nodes:write')
            ->name('nodes.update');
        Route::delete('/nodes/{type}/{node}', [GraphNodeController::class, 'destroy'])
            ->middleware('api.ability:nodes:write')
            ->name('nodes.destroy');

        Route::get('/edges', [GraphEdgeController::class, 'index'])
            ->middleware('api.ability:edges:read')
            ->name('edges.index');
        Route::post('/edges', [GraphEdgeController::class, 'store'])
            ->middleware(['api.ability:edges:write', 'api.idempotent'])
            ->name('edges.store');
        Route::patch('/edges/{edge}', [GraphEdgeController::class, 'update'])
            ->middleware('api.ability:edges:write')
            ->name('edges.update');
        Route::delete('/edges/{edge}', [GraphEdgeController::class, 'destroy'])
            ->middleware('api.ability:edges:write')
            ->name('edges.destroy');

        Route::get('/spaces', [SpaceController::class, 'index'])
            ->middleware('api.ability:nodes:read')
            ->name('spaces.index');
        Route::post('/spaces', [SpaceController::class, 'store'])
            ->middleware(['api.ability:nodes:write', 'api.idempotent'])
            ->name('spaces.store');
        Route::get('/spaces/{space}', [SpaceController::class, 'show'])
            ->middleware('api.ability:nodes:read')
            ->name('spaces.show');
        Route::get('/spaces/{space}/nodes', SpaceNodeController::class)
            ->middleware('api.ability:nodes:read')
            ->name('spaces.nodes.index');

        Route::get('/threads/{thread}', [ThreadController::class, 'show'])
            ->middleware('api.ability:nodes:read')
            ->name('threads.show');
        Route::get('/threads/{thread}/nodes', ThreadNodeController::class)
            ->middleware('api.ability:nodes:read')
            ->name('threads.nodes.index');
        Route::get('/threads/{thread}/posts', [ThreadPostController::class, 'index'])
            ->middleware('api.ability:nodes:read')
            ->name('threads.posts.index');

        Route::get('/posts/{post}', [PostController::class, 'show'])
            ->middleware('api.ability:nodes:read')
            ->name('posts.show');
        Route::get('/posts/{post}/nodes', PostNodeController::class)
            ->middleware('api.ability:nodes:read')
            ->name('posts.nodes.index');

        Route::prefix('channels')
            ->middleware('api.ability:channels:manage')
            ->group(function (): void {
                Route::get('/', [ChannelController::class, 'index'])->name('channels.index');
                Route::post('/', [ChannelController::class, 'store'])->name('channels.store');
                Route::patch('/{channel}', [ChannelController::class, 'update'])->name('channels.update');
                Route::delete('/{channel}', [ChannelController::class, 'destroy'])->name('channels.destroy');
                Route::get('/{channel}/connections', [ChannelConnectionController::class, 'index'])->name('channels.connections.index');
                Route::post('/{channel}/connections', [ChannelConnectionController::class, 'store'])->name('channels.connections.store');
                Route::patch('/{channel}/connections/{connection}', [ChannelConnectionController::class, 'update'])->name('channels.connections.update');
                Route::delete('/{channel}/connections/{connection}', [ChannelConnectionController::class, 'destroy'])->name('channels.connections.destroy');
                Route::get('/{channel}/routes', [ChannelRouteController::class, 'index'])->name('channels.routes.index');
                Route::post('/{channel}/routes', [ChannelRouteController::class, 'store'])->name('channels.routes.store');
                Route::patch('/{channel}/routes/{route}', [ChannelRouteController::class, 'update'])->name('channels.routes.update');
                Route::delete('/{channel}/routes/{route}', [ChannelRouteController::class, 'destroy'])->name('channels.routes.destroy');
                Route::get('/{channel}/routes/{route}/addresses', [ChannelAddressController::class, 'index'])->name('channels.routes.addresses.index');
                Route::post('/{channel}/routes/{route}/addresses', [ChannelAddressController::class, 'store'])->name('channels.routes.addresses.store');
                Route::patch('/{channel}/routes/{route}/addresses/{address}', [ChannelAddressController::class, 'update'])->name('channels.routes.addresses.update');
                Route::delete('/{channel}/routes/{route}/addresses/{address}', [ChannelAddressController::class, 'destroy'])->name('channels.routes.addresses.destroy');
            });
    });
});
