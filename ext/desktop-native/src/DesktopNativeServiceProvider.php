<?php

namespace Figurate\DesktopNative;

use App\Support\Runtime\AppRuntime;
use Figurate\DesktopNative\Http\Middleware\InjectDesktopNativeAssets;
use Figurate\WebView\Http\Middleware\HandleInertiaRequests;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class DesktopNativeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        app(AppRuntime::class)->registerRootView('desktop', 'desktop-native::app');
        app(AppRuntime::class)->claimNativeHost('desktop');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(dirname(__DIR__).'/resources/views', 'desktop-native');

        if (runtime() !== 'desktop') {
            return;
        }

        $this->app->booted(function (): void {
            $router = $this->app->make(Router::class);

            $router->pushMiddlewareToGroup('web', InjectDesktopNativeAssets::class);
            $router->pushMiddlewareToGroup('web', HandleInertiaRequests::class);
        });
    }
}
