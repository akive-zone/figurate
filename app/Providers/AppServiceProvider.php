<?php

namespace App\Providers;

use App\Http\Middleware\EnsureDeviceUser;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
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
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        }

        $router = $this->app->make(Router::class);

        if (! $this->isNativeRuntime()) {
            $router->pushMiddlewareToGroup('web', EnsureDeviceUser::class);
        }
    }

    protected function isNativeRuntime(): bool
    {
        return \app_is_native_runtime();
    }

    /**
     * @return array<class-string>
     */
    protected function nativeProviders(): array
    {
        return [
            \App\Providers\Native\StudioPanelProvider::class,
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
