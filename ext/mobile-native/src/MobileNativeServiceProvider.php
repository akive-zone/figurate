<?php

namespace Figurate\MobileNative;

use App\Support\Runtime\AppRuntime;
use Figurate\MobileNative\Http\Middleware\InjectMobileNativeAssets;
use Figurate\WebView\Http\Middleware\HandleInertiaRequests;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class MobileNativeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        app(AppRuntime::class)->registerRootView('mobile', 'mobile-native::app');
        app(AppRuntime::class)->claimNativeHost('mobile');

        if (runtime() === 'mobile') {
            $this->app->register(ControlPanelProvider::class);
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(dirname(__DIR__).'/routes/web.php');
        $this->loadViewsFrom(dirname(__DIR__).'/resources/views', 'mobile-native');

        if (runtime() !== 'mobile') {
            return;
        }

        $this->app->booted(function (): void {
            $router = $this->app->make(Router::class);

            $router->pushMiddlewareToGroup('web', InjectMobileNativeAssets::class);
            $router->pushMiddlewareToGroup('web', HandleInertiaRequests::class);
        });
    }
}
