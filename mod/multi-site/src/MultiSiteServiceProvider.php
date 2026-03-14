<?php

namespace Figurate\MultiSite;

use Illuminate\Support\ServiceProvider;

class MultiSiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/multi-site.php', 'figurate-multi-site');

        $this->app->singleton(MultiSiteDefinition::class, function (): MultiSiteDefinition {
            $config = config('figurate-multi-site', []);

            return new MultiSiteDefinition(
                driver: (string) ($config['driver'] ?? 'stancl/tenancy'),
                databaseStrategy: (string) ($config['database_strategy'] ?? 'separate'),
                separateDatabase: (bool) ($config['separate_database'] ?? true),
                identifiers: array_values(array_filter($config['identifiers'] ?? ['domain'])),
            );
        });
    }

    public function boot(): void {}
}
