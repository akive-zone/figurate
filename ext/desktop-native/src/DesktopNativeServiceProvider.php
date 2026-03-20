<?php

namespace Figurate\DesktopNative;

use Illuminate\Support\ServiceProvider;

class DesktopNativeServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadViewsFrom(dirname(__DIR__).'/resources/views', 'desktop-native');
    }
}
