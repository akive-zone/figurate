<?php

use Illuminate\Support\Facades\Route;

Route::middleware('api')
    ->prefix('api')
    ->group(function (): void {
        Route::webhooks('channel-routes/{route}/inbound', 'channel_route_inbound');
    });
