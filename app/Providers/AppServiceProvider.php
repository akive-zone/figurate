<?php

namespace App\Providers;

use App\Http\Middleware\EnsureDeviceUser;
use App\Models\Server\Channel;
use App\Models\Server\Message;
use App\Models\Server\Post;
use App\Models\Server\Profile;
use App\Models\Server\Store;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $providers = $this->isNativeRuntime()
            ? []
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
        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            return 'Database\\Factories\\'.class_basename($modelName).'Factory';
        });

        Passport::tokensCan([
            'chat' => 'Use chat-oriented API capabilities.',
            'studio' => 'Use the studio API.',
            'mcp:use' => 'Use the Figurate MCP transport.',
            'acp:use' => 'Use the ACP transport.',
            'a2a:message.send' => 'Send A2A messages.',
            'a2a:task.read' => 'Read A2A task state.',
            'a2a:task.cancel' => 'Cancel A2A tasks.',
            'a2a:push.config.manage' => 'Manage A2A push notification configuration.',
        ]);

        if (! $this->isNativeRuntime()) {
            $this->loadMigrationsFrom(database_path('migrations/server'));

            Relation::morphMap([
                'channel' => Channel::class,
                'message' => Message::class,
                'post' => Post::class,
                'profile' => Profile::class,
                'store' => Store::class,
                'thread' => Thread::class,
                'user' => User::class,
            ]);

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            $router = $this->app->make(Router::class);

            $router->pushMiddlewareToGroup('web', EnsureDeviceUser::class);
            $this->prioritizeDeviceMiddlewareBeforeAuth($router);
        }
    }

    protected function prioritizeDeviceMiddlewareBeforeAuth(Router $router): void
    {
        if (in_array(EnsureDeviceUser::class, $router->middlewarePriority, true)) {
            return;
        }

        $authIndex = array_search(AuthenticatesRequests::class, $router->middlewarePriority, true);

        if ($authIndex === false) {
            array_unshift($router->middlewarePriority, EnsureDeviceUser::class);

            return;
        }

        array_splice($router->middlewarePriority, $authIndex, 0, [EnsureDeviceUser::class]);
    }

    protected function isNativeRuntime(): bool
    {
        return \app_is_native_runtime();
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
            \App\Providers\Server\ChatServiceProvider::class,
            \App\Providers\Server\ControlPanelProvider::class,
        ];
    }
}
