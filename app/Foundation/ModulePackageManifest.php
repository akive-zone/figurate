<?php

namespace App\Foundation;

use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Collection;

class ModulePackageManifest extends PackageManifest
{
    public function build(): void
    {
        $packages = (new Collection($this->installedPackages()))
            ->merge($this->modulePackages())
            ->keyBy('name')
            ->values()
            ->all();

        $ignoreAll = in_array('*', $ignore = $this->packagesToIgnore(), true);

        $this->write((new Collection($packages))
            ->mapWithKeys(function (array $package): array {
                return [$this->format($package['name']) => $package['extra']['laravel'] ?? []];
            })
            ->each(function (array $configuration) use (&$ignore): void {
                $ignore = array_merge($ignore, $configuration['dont-discover'] ?? []);
            })
            ->reject(function (array $configuration, string $package) use ($ignore, $ignoreAll): bool {
                return $ignoreAll || in_array($package, $ignore, true);
            })
            ->filter()
            ->all());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function installedPackages(): array
    {
        if (! $this->files->exists($path = $this->vendorPath.'/composer/installed.json')) {
            return [];
        }

        $installed = json_decode($this->files->get($path), true);

        return $installed['packages'] ?? $installed;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function modulePackages(): array
    {
        return collect($this->mergePluginIncludePaths())
            ->filter(fn (string $path): bool => $this->files->exists($path))
            ->map(function (string $path): ?array {
                $package = json_decode($this->files->get($path), true);

                if (! is_array($package) || ! isset($package['name']) || ! is_string($package['name'])) {
                    return null;
                }

                return $package;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function mergePluginIncludePaths(): array
    {
        if (! $this->files->exists($composerJsonPath = $this->basePath.'/composer.json')) {
            return [];
        }

        $composer = json_decode($this->files->get($composerJsonPath), true);

        if (! is_array($composer)) {
            return [];
        }

        $patterns = $composer['extra']['merge-plugin']['include'] ?? [];

        if (! is_array($patterns)) {
            return [];
        }

        return collect($patterns)
            ->filter(fn (mixed $pattern): bool => is_string($pattern) && $pattern !== '')
            ->flatMap(function (string $pattern): array {
                $matches = glob($this->basePath.'/'.$pattern);

                return is_array($matches) ? $matches : [];
            })
            ->unique()
            ->values()
            ->all();
    }
}
