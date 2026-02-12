<?php

use App\Http\Middleware\EnsureDeviceUser;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
        using: function (): void {},
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', EnsureDeviceUser::class);
        $middleware->appendToGroup('web', HandleInertiaRequests::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
