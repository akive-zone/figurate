<?php

$providers = [
    App\Providers\AppServiceProvider::class,
    App\Providers\Server\AuthServiceProvider::class,
];

if (config('app.context') == 'native') {
    $providers[] = App\Providers\Native\StudioPanelProvider::class;
    $providers[] = App\Providers\Native\SignalPanelProvider::class;
}

if (config('app.context') == 'web' || config('app.context') == null) {
    $providers[] = App\Providers\Browser\SignalPanelProvider::class;
    $providers[] = App\Providers\Browser\StudioPanelProvider::class;
    $providers[] = App\Providers\Browser\StationPanelProvider::class;
}

return $providers;
