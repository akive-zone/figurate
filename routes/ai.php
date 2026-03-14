<?php

use App\Ai\Gateways\Mcp\Servers\FigurateServer;
use App\Http\Middleware\EnsureDeviceUser;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/figurate', FigurateServer::class)
    ->middleware([EnsureDeviceUser::class, 'auth:sanctum']);
