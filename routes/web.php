<?php

use App\Ai\Gateways\Mcp\Servers\FigurateServer;
use App\Http\Controllers\Native\Web\ChannelController as NativeChannelController;
use App\Http\Controllers\Server\Api\A2a\AgentCardController;
use App\Http\Controllers\Server\Web\ChannelController as ServerChannelController;
use App\Http\Controllers\Server\Web\PasskeyController as ServerPasskeyController;
use App\Http\Controllers\Server\Web\SocialiteController;
use App\Http\Middleware\EnsureDeviceUser;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

if (\app_is_native_runtime()) {
    Route::get('/', function () {
        return view('native.launcher');
    })->name('launcher');

    Route::prefix('chat')
        ->name('chat.')
        ->group(function () {
            Route::get('/', [NativeChannelController::class, 'index'])->name('index');
            Route::get('/create', [NativeChannelController::class, 'create'])->name('create');
            Route::get('/c/{channel}', [NativeChannelController::class, 'show'])->name('show');
            Route::get('/c/{channel}/t/{thread}', [NativeChannelController::class, 'showThread'])->name('thread');
        });

    Route::fallback(function () {
        return redirect()->to('/chat');
    });
} else {
    Route::get('/.well-known/agent-card', AgentCardController::class)->name('a2a.agent-card');

    Mcp::web('/mcp/figurate', FigurateServer::class)
        ->middleware([EnsureDeviceUser::class, 'auth:sanctum']);

    Route::prefix('auth')
        ->name('auth.')
        ->group(function () {
            Route::get('{provider}/redirect', [SocialiteController::class, 'redirect'])
                ->name('redirect');
            Route::get('{provider}/callback', [SocialiteController::class, 'callback'])
                ->name('callback');
        });

    Route::passkeys();

    Route::middleware('auth')
        ->prefix('passkeys/manage')
        ->name('passkeys.manage.')
        ->group(function () {
            Route::get('/generate-options', [ServerPasskeyController::class, 'generateOptions'])->name('generate-options');
            Route::post('/', [ServerPasskeyController::class, 'store'])->name('store');
            Route::delete('/{passkey}', [ServerPasskeyController::class, 'destroy'])->name('destroy');
        });

    Route::middleware(EnsureDeviceUser::class)
        ->name('chat.')
        ->group(function () {
            Route::get('/', [ServerChannelController::class, 'index'])->name('index');
            Route::get('/create', [ServerChannelController::class, 'index'])->name('create');
            Route::get('/c/{channel}', [ServerChannelController::class, 'show'])->name('show');
            Route::get('/c/{channel}/t/{thread}', [ServerChannelController::class, 'showThread'])->name('thread');
        });
}
