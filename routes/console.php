<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

foreach ([
    ...glob(base_path('mod/*/routes/console.php')) ?: [],
    ...glob(base_path('ext/*/routes/console.php')) ?: [],
] as $consoleRoutesPath) {
    require $consoleRoutesPath;
}
