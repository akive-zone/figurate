<?php

use App\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [__DIR__.'/../routes/webview.php', __DIR__.'/../routes/webhook.php'],
        api: [__DIR__.'/../routes/rest.php', __DIR__.'/../routes/ai.php'],
        channels: __DIR__.'/../routes/broadcast.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '/',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }
        });
    })->create();
