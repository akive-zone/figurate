<?php

namespace App\Providers;

use App\Contracts\Users\UserRepository;
use App\Models\Server\ApiPersonalAccessToken;
use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Store;
use App\Models\Server\Thread;
use App\Models\Server\User;
use App\Repositories\Users\EloquentUserRepository;
use App\Support\Channels\ChannelDriverRegistry;
use App\Support\Runtime\AppRuntime;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AppRuntime::class);
        $this->app->singleton(ChannelDriverRegistry::class);
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
        Sanctum::usePersonalAccessTokenModel(ApiPersonalAccessToken::class);

        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            return 'Database\\Factories\\'.class_basename($modelName).'Factory';
        });

        Passport::tokensCan([
            'compose' => 'Use message-oriented API capabilities.',
            'mcp:use' => 'Use the Figurate MCP transport.',
            'acp:use' => 'Use the ACP transport.',
            'a2a:message.send' => 'Send A2A messages.',
            'a2a:task.read' => 'Read A2A task state.',
            'a2a:task.cancel' => 'Cancel A2A tasks.',
            'a2a:push.config.manage' => 'Manage A2A push notification configuration.',
            'nodes:read' => 'Read Space, Thread, and Post nodes.',
            'nodes:write' => 'Create, update, and delete Space, Thread, and Post nodes.',
            'edges:read' => 'Explore graph edges.',
            'edges:write' => 'Create, update, and delete graph edges.',
            'forms:submit' => 'Submit work through the Form API.',
            'invocations:read' => 'Read invocation turns.',
            'channels:manage' => 'Manage channels and remote routes.',
            'credentials:manage' => 'Manage scoped API credentials.',
        ]);

        $this->loadMigrationsFrom(database_path('migrations/server'));

        Relation::morphMap([
            'space' => Space::class,
            'post' => Post::class,
            'store' => Store::class,
            'thread' => Thread::class,
            'user' => User::class,
            'channel' => Channel::class,
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
