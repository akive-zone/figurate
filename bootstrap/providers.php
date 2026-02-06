<?php

$providers = [
    App\Providers\AppServiceProvider::class,
    App\Providers\Server\AuthServiceProvider::class,
];

if (config('app.context') == 'native') {
    $providers[] = App\Providers\Native\StudioPanelProvider::class;
}

if (config('app.context') == 'web' || config('app.context') == null) {
    $providers[] = App\Providers\Browser\StudioPanelProvider::class;
    $providers[] = App\Providers\Browser\StationPanelProvider::class;
}

return $providers;
