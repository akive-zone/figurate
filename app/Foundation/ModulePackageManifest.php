<?php

namespace App\Foundation;

use App\Support\ComposerLocalModules;
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
        return collect(ComposerLocalModules::at($this->basePath)->resolvedMergePluginIncludes())
            ->filter(fn (string $path): bool => $this->files->exists($this->basePath.'/'.$path))
            ->map(function (string $path): ?array {
                $package = json_decode($this->files->get($this->basePath.'/'.$path), true);

                if (! is_array($package) || ! isset($package['name']) || ! is_string($package['name'])) {
                    return null;
                }

                return $package;
            })
            ->filter()
            ->values()
            ->all();
    }
}
