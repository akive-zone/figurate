<?php

namespace Figurate\MultiSpace;

use Illuminate\Support\ServiceProvider;

class MultiSpaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/multi-space.php', 'figurate-multi-space');

        $this->app->singleton(MultiSpaceDefinition::class, function (): MultiSpaceDefinition {
            $config = config('figurate-multi-space', []);

            return new MultiSpaceDefinition(
                driver: (string) ($config['driver'] ?? 'spatie/laravel-multitenancy'),
                databaseStrategy: (string) ($config['database_strategy'] ?? 'shared'),
                sharedDatabase: (bool) ($config['shared_database'] ?? true),
                requiresSiteContext: (bool) ($config['requires_site_context'] ?? true),
                parentLayer: (string) ($config['parent_layer'] ?? 'multi_site'),
                scopeColumns: array_values(array_filter($config['scope_columns'] ?? ['site_id', 'space_id'])),
            );
        });
    }

    public function boot(): void {}
}
