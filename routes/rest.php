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
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\GraphEdgeController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PostTurnsController;
use App\Http\Controllers\Api\RobotUserController;
use App\Http\Controllers\Api\SpaceController;
use App\Http\Controllers\Api\SpacePostController;
use App\Http\Controllers\Api\SpaceThreadController;
use App\Http\Controllers\Api\ThreadController;
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

Route::post('users', [RobotUserController::class, 'store'])
    ->middleware(['auth:sanctum,passport', EnsureTransportUser::class.':subject'])
    ->name('api.users.store');

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
    Route::get('/{space}/posts', [SpacePostController::class, 'index'])->name('api.spaces.posts.index');
    Route::post('/{space}/posts', [SpacePostController::class, 'store'])->name('api.spaces.posts.store');
    Route::get('/{space}/threads', [SpaceThreadController::class, 'index'])->name('api.spaces.threads.index');
    Route::post('/{space}/threads', [SpaceThreadController::class, 'store'])->name('api.spaces.threads.store');
});

Route::prefix('threads')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::get('/{thread}', [ThreadController::class, 'show'])->name('api.threads.show');
    Route::post('/{thread}/posts', [ThreadPostController::class, 'store'])->name('api.threads.posts.store');
    Route::get('/{thread}/posts/{post}/turns', [PostTurnsController::class, 'index'])->name('api.threads.posts.turns.index');
});

Route::prefix('posts')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::get('/{post}', [PostController::class, 'show'])->name('api.posts.show');
    Route::get('/{post}/turns', [PostTurnsController::class, 'index'])->name('api.posts.turns.index');
});

Route::prefix('graph')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::get('/edges', [GraphEdgeController::class, 'index'])->name('api.graph.edges.index');
    Route::post('/edges', [GraphEdgeController::class, 'store'])->name('api.graph.edges.store');
});

Route::prefix('form')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::post('/', [FormController::class, 'store'])->name('api.form.store');
});
