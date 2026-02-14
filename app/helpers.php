<?php

if (! function_exists('app_is_native_runtime')) {
    function app_is_native_runtime(): bool
    {
        $nativeRuntimeFlag = $_ENV['NATIVEPHP_RUNNING'] ?? $_SERVER['NATIVEPHP_RUNNING'] ?? getenv('NATIVEPHP_RUNNING');

        if (is_bool($nativeRuntimeFlag)) {
            return $nativeRuntimeFlag;
        }

        if (is_string($nativeRuntimeFlag)) {
            return in_array(strtolower($nativeRuntimeFlag), ['1', 'true', 'yes', 'on'], true);
        }

        if (function_exists('config')) {
            if ((bool) config('nativephp-internal.running')) {
                return true;
            }

            if (config('app.context') === 'native') {
                return true;
            }
        }

        return false;
    }
}
