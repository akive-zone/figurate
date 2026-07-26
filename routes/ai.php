<?php

use App\Http\Controllers\Api\A2a\AgentCardController;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::get('/.well-known/agent-card', AgentCardController::class)->name('a2a.agent-card');

Mcp::oauthRoutes();
