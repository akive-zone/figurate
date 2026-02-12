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
        $providers = $this->isNativeRuntime()
            ? $this->nativeProviders()
            : $this->serverProviders();

        foreach ($providers as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->isNativeRuntime()) {
            $this->loadMigrationsFrom(database_path('migrations/native'));
        } else {
            $this->loadMigrationsFrom(database_path('migrations/server'));
        }
    }

    protected function isNativeRuntime(): bool
    {
        // APP_CONTEXT=native is treated as an explicit local override.
        if (config('app.context') === 'native') {
            return true;
        }

        return (bool) config('nativephp-internal.running');
    }

    /**
     * @return array<class-string>
     */
    protected function nativeProviders(): array
    {
        return [
            \App\Providers\Native\StudioPanelProvider::class,
            \App\Providers\Native\SignalPanelProvider::class,
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function serverProviders(): array
    {
        return [
            \ApiPlatform\Laravel\ApiPlatformProvider::class,
            \ApiPlatform\Laravel\ApiPlatformDeferredProvider::class,
            \ApiPlatform\Laravel\Eloquent\ApiPlatformEventProvider::class,
            \App\Providers\Server\AuthServiceProvider::class,
            \App\Providers\Browser\SignalPanelProvider::class,
            \App\Providers\Browser\StudioPanelProvider::class,
            \App\Providers\Browser\StationPanelProvider::class,
        ];
    }
}
