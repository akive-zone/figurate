<?php

namespace Figurate\MobileNative;

use Illuminate\Support\ServiceProvider;

class MobileNativeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (\app_is_native_runtime()) {
            $this->app->register(ControlPanelProvider::class);
        }
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(dirname(__DIR__).'/routes/web.php');
        $this->loadViewsFrom(dirname(__DIR__).'/resources/views', 'mobile-native');
    }
}
