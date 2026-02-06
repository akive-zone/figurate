<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('nativephp-internal.running')) {
            $this->loadMigrationsFrom(database_path('migrations/native'));
        } else {
            $this->loadMigrationsFrom(database_path('migrations/server'));
        }
    }
}
