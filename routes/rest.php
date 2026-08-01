<?php

use App\Http\Controllers\Api\ChannelAddressController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\ChannelRouteController;
use App\Http\Controllers\Api\GraphEdgeController;
use App\Http\Controllers\Api\GraphNodeController;
use App\Http\Controllers\Api\OpenApiController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PostNodeController;
use App\Http\Controllers\Api\RobotUserController;
use App\Http\Controllers\Api\SpaceController;
use App\Http\Controllers\Api\SpaceNodeController;
use App\Http\Controllers\Api\ThreadController;
use App\Http\Controllers\Api\ThreadNodeController;
use App\Http\Middleware\EnsureTransportUser;
use Illuminate\Support\Facades\Route;

Route::get('openapi.json', OpenApiController::class)->name('api.openapi');

Route::prefix('nodes')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::post('/', [GraphNodeController::class, 'store'])
        ->middleware(['api.ability:nodes:write', 'api.idempotent'])
        ->name('api.nodes.store');
    Route::get('/{type}/{node}', [GraphNodeController::class, 'show'])
        ->middleware('api.ability:nodes:read')
        ->name('api.nodes.show');
    Route::patch('/{type}/{node}', [GraphNodeController::class, 'update'])
        ->middleware('api.ability:nodes:write')
        ->name('api.nodes.update');
    Route::delete('/{type}/{node}', [GraphNodeController::class, 'destroy'])
        ->middleware('api.ability:nodes:write')
        ->name('api.nodes.destroy');
});

Route::prefix('edges')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::get('/', [GraphEdgeController::class, 'index'])
        ->middleware('api.ability:edges:read')
        ->name('api.edges.index');
    Route::post('/', [GraphEdgeController::class, 'store'])
        ->middleware(['api.ability:edges:write', 'api.idempotent'])
        ->name('api.edges.store');
    Route::patch('/{edge}', [GraphEdgeController::class, 'update'])
        ->middleware('api.ability:edges:write')
        ->name('api.edges.update');
    Route::delete('/{edge}', [GraphEdgeController::class, 'destroy'])
        ->middleware('api.ability:edges:write')
        ->name('api.edges.destroy');
});

Route::prefix('users')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::post('/', [RobotUserController::class, 'store'])
        ->middleware(['auth:sanctum,passport', EnsureTransportUser::class.':subject'])
        ->name('api.users.store');
});

Route::prefix('spaces')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::get('/', [SpaceController::class, 'index'])->middleware('api.ability:nodes:read')->name('api.spaces.index');
    Route::post('/', [SpaceController::class, 'store'])->middleware(['api.ability:nodes:write', 'api.idempotent'])->name('api.spaces.store');
    Route::get('/{space}', [SpaceController::class, 'show'])->middleware('api.ability:nodes:read')->name('api.spaces.show');
    Route::get('/{space}/nodes', SpaceNodeController::class)->middleware('api.ability:nodes:read')->name('api.spaces.nodes.index');
});

Route::prefix('threads')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::get('/', [ThreadController::class, 'index'])->middleware('api.ability:nodes:read')->name('api.threads.index');
    Route::post('/', [ThreadController::class, 'store'])->middleware(['api.ability:nodes:write', 'api.idempotent'])->name('api.threads.store');
    Route::get('/{thread}', [ThreadController::class, 'show'])->middleware('api.ability:nodes:read')->name('api.threads.show');
    Route::get('/{thread}/nodes', ThreadNodeController::class)->middleware('api.ability:nodes:read')->name('api.threads.nodes.index');
});

Route::prefix('posts')->middleware(['auth:sanctum,passport'])->group(function (): void {
    Route::get('/', [PostController::class, 'index'])->middleware('api.ability:nodes:read')->name('api.posts.index');
    Route::post('/', [PostController::class, 'store'])->middleware(['api.ability:nodes:write', 'api.idempotent'])->name('api.posts.store');
    Route::get('/{post}', [PostController::class, 'show'])->middleware('api.ability:nodes:read')->name('api.posts.show');
    Route::get('/{post}/nodes', PostNodeController::class)->middleware('api.ability:nodes:read')->name('api.posts.nodes.index');
});

Route::prefix('channels')->middleware(['auth:sanctum,passport', 'api.ability:channels:manage'])->group(function (): void {
    Route::get('/', [ChannelController::class, 'index'])->name('api.channels.index');
    Route::post('/', [ChannelController::class, 'store'])->name('api.channels.store');
    Route::patch('/{channel}', [ChannelController::class, 'update'])->name('api.channels.update');
    Route::delete('/{channel}', [ChannelController::class, 'destroy'])->name('api.channels.destroy');
    Route::get('/{channel}/routes', [ChannelRouteController::class, 'index'])->name('api.channels.routes.index');
    Route::post('/{channel}/routes', [ChannelRouteController::class, 'store'])->name('api.channels.routes.store');
    Route::patch('/{channel}/routes/{route}', [ChannelRouteController::class, 'update'])->name('api.channels.routes.update');
    Route::delete('/{channel}/routes/{route}', [ChannelRouteController::class, 'destroy'])->name('api.channels.routes.destroy');
    Route::get('/{channel}/routes/{route}/addresses', [ChannelAddressController::class, 'index'])->name('api.channels.routes.addresses.index');
    Route::post('/{channel}/routes/{route}/addresses', [ChannelAddressController::class, 'store'])->name('api.channels.routes.addresses.store');
    Route::patch('/{channel}/routes/{route}/addresses/{address}', [ChannelAddressController::class, 'update'])->name('api.channels.routes.addresses.update');
    Route::delete('/{channel}/routes/{route}/addresses/{address}', [ChannelAddressController::class, 'destroy'])->name('api.channels.routes.addresses.destroy');
});
