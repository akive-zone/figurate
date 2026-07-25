<?php

namespace Figurate\ControlPanel;

use Illuminate\Support\ServiceProvider;

class ControlPanelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (runtime() === 'server') {
            $this->app->register(ControlPanelProvider::class);
        }
    }
}
