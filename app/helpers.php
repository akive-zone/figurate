<?php

use App\Support\Runtime\AppRuntime;

if (! function_exists('runtime')) {
    function runtime(): string
    {
        return app(AppRuntime::class)->host();
    }
}
