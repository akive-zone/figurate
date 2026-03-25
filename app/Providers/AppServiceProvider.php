<?php

namespace App\Providers;

use App\Contracts\Users\UserRepository;
use App\Models\Server\Message;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Store;
use App\Models\Server\Thread;
use App\Models\Server\User;
use App\Repositories\Users\EloquentUserRepository;
use App\Support\Runtime\AppRuntime;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AppRuntime::class);
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);

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
            'composer' => 'Use message-oriented API capabilities.',
            'mcp:use' => 'Use the Figurate MCP transport.',
            'acp:use' => 'Use the ACP transport.',
            'a2a:message.send' => 'Send A2A messages.',
            'a2a:task.read' => 'Read A2A task state.',
            'a2a:task.cancel' => 'Cancel A2A tasks.',
            'a2a:push.config.manage' => 'Manage A2A push notification configuration.',
        ]);

        $this->loadMigrationsFrom(database_path('migrations/server'));

        Relation::morphMap([
            'space' => Space::class,
            'message' => Message::class,
            'post' => Post::class,
            'store' => Store::class,
            'thread' => Thread::class,
            'user' => User::class,
        ]);

    }

    protected function isNativeRuntime(): bool
    {
        return app(AppRuntime::class)->isNative();
    }

    /**
     * @return array<class-string>
     */
    protected function serverProviders(): array
    {
        return [

        ];
    }
}
