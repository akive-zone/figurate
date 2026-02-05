<?php

$providers = [
    App\Providers\AppServiceProvider::class,
];

if (config('app.context') == 'native') {
    $providers[] = App\Providers\Native\StudioPanelProvider::class;
}

if (config('app.context') == 'web' || config('app.context') == null) {
    $providers[] = App\Providers\Web\StudioPanelProvider::class;
    $providers[] = App\Providers\Web\StationPanelProvider::class;
}

return $providers;
