<?php

use App\Ai\Gateways\Mcp\Servers\FigurateServer;
use App\Http\Controllers\Api\A2a\AgentCardController;
use App\Http\Middleware\EnsureTokenAbility;
use App\Http\Middleware\EnsureTransportUser;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

if (runtime() === 'server') {
    Route::get('/.well-known/agent-card', AgentCardController::class)->name('a2a.agent-card');

    Route::passkeys();

    Mcp::oauthRoutes();

    Mcp::web('/mcp/figurate', FigurateServer::class)
        ->middleware(['auth:sanctum,passport', EnsureTransportUser::class, EnsureTokenAbility::class.':mcp:use']);
}
