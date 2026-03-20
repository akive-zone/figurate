<?php

namespace App\Console\Commands;

use App\Support\ComposerLocalModules;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class ManageComposerLocalModulesCommand extends Command
{
    protected $signature = 'app:plugins
        {action=list : list, enable, disable, enable-all, disable-all, sync}
        {targets?* : Package names, directory names, or composer.json paths}';

    protected $description = 'Manage enabled mod/* and ext/* packages in composer.local.json';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modules = ComposerLocalModules::at(base_path());
        $action = (string) $this->argument('action');

        return match ($action) {
            'list' => $this->listPackages($modules),
            'enable' => $this->enablePackages($modules, false),
            'disable' => $this->disablePackages($modules, false),
            'enable-all' => $this->enablePackages($modules, true),
            'disable-all' => $this->disablePackages($modules, true),
            'sync' => $this->syncPackages($modules),
            default => self::FAILURE,
        };
    }

    protected function listPackages(ComposerLocalModules $modules): int
    {
        $this->table(
            ['Scope', 'Package', 'Path', 'Enabled'],
            collect($modules->packages())
                ->map(fn (array $package): array => [
                    $package['scope'],
                    $package['name'],
                    $package['path'],
                    $package['enabled'] ? 'yes' : 'no',
                ])
                ->all(),
        );

        return self::SUCCESS;
    }

    protected function enablePackages(ComposerLocalModules $modules, bool $all): int
    {
        $packages = collect($modules->packages());

        if ($all) {
            $modules->writeEnabledPackagePaths($packages->pluck('path')->all());

            return $this->reportUpdatedPackages($modules, 'Enabled all packages.');
        }

        $selectedPackages = $this->selectPackages($packages);

        if ($selectedPackages === null) {
            return self::FAILURE;
        }

        $modules->writeEnabledPackagePaths(
            $packages
                ->filter(fn (array $package): bool => $package['enabled'])
                ->pluck('path')
                ->merge($selectedPackages->pluck('path'))
                ->unique()
                ->values()
                ->all(),
        );

        return $this->reportUpdatedPackages(
            $modules,
            'Enabled: '.$selectedPackages->pluck('name')->implode(', '),
        );
    }

    protected function disablePackages(ComposerLocalModules $modules, bool $all): int
    {
        if ($all) {
            $modules->writeEnabledPackagePaths([]);

            return $this->reportUpdatedPackages($modules, 'Disabled all packages.');
        }

        $packages = collect($modules->packages());
        $selectedPackages = $this->selectPackages($packages);

        if ($selectedPackages === null) {
            return self::FAILURE;
        }

        $selectedPaths = $selectedPackages->pluck('path')->all();

        $modules->writeEnabledPackagePaths(
            $packages
                ->reject(fn (array $package): bool => in_array($package['path'], $selectedPaths, true))
                ->filter(fn (array $package): bool => $package['enabled'])
                ->pluck('path')
                ->values()
                ->all(),
        );

        return $this->reportUpdatedPackages(
            $modules,
            'Disabled: '.$selectedPackages->pluck('name')->implode(', '),
        );
    }

    protected function syncPackages(ComposerLocalModules $modules): int
    {
        $modules->writeEnabledPackagePaths($modules->enabledPackagePaths());

        return $this->reportUpdatedPackages($modules, 'Synchronized composer.local.json.');
    }

    /**
     * @param  Collection<int, array{name: string, path: string, scope: string, enabled: bool}>  $packages
     * @return Collection<int, array{name: string, path: string, scope: string, enabled: bool}>|null
     */
    protected function selectPackages(Collection $packages): ?Collection
    {
        $targets = collect((array) $this->argument('targets'))
            ->map(fn (mixed $target): string => trim((string) $target))
            ->filter()
            ->values();

        if ($targets->isEmpty()) {
            $this->error('Specify one or more package names, directory names, or composer.json paths.');

            return null;
        }

        $selectedPackages = $packages
            ->filter(function (array $package) use ($targets): bool {
                return $targets->contains(function (string $target) use ($package): bool {
                    return $target === $package['name']
                        || $target === $package['path']
                        || $target === basename(dirname($package['path']));
                });
            })
            ->values();

        if ($selectedPackages->count() !== $targets->count()) {
            $resolvedTargets = $selectedPackages
                ->flatMap(fn (array $package): array => [
                    $package['name'],
                    $package['path'],
                    basename(dirname($package['path'])),
                ])
                ->unique();

            $missingTargets = $targets
                ->reject(fn (string $target): bool => $resolvedTargets->contains($target))
                ->values();

            $this->error('Unknown packages: '.$missingTargets->implode(', '));

            return null;
        }

        return $selectedPackages;
    }

    protected function reportUpdatedPackages(ComposerLocalModules $modules, string $message): int
    {
        $this->info($message);
        $this->line('Enabled paths:');

        foreach ($modules->enabledPackagePaths() as $path) {
            $this->line(' - '.$path);
        }

        $this->newLine();
        $this->warn('Run `composer update` if dependencies changed, otherwise `composer dump-autoload` is enough.');

        return self::SUCCESS;
    }
}
