<?php

namespace App\Support;

class ComposerLocalModules
{
    public function __construct(
        protected string $basePath,
    ) {}

    public static function at(string $basePath): self
    {
        return new self($basePath);
    }

    /**
     * @return list<array{name: string, path: string, scope: string, enabled: bool}>
     */
    public function packages(): array
    {
        $availablePaths = $this->availablePackagePaths();
        $enabledPaths = $this->enabledPackagePaths();

        return collect($availablePaths)
            ->map(function (string $path) use ($enabledPaths): ?array {
                $package = $this->readJson($this->absolutePath($path));

                if (! is_array($package)) {
                    return null;
                }

                $name = $package['name'] ?? null;

                if (! is_string($name) || $name === '') {
                    return null;
                }

                return [
                    'name' => $name,
                    'path' => $path,
                    'scope' => str_starts_with($path, 'mod/') ? 'mod' : 'ext',
                    'enabled' => in_array($path, $enabledPaths, true),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function enabledPackagePaths(): array
    {
        $availablePaths = $this->availablePackagePaths();

        return collect($this->resolvedMergePluginIncludes($this->composerLocalPath()))
            ->map(fn (string $path): string => $this->relativePath($path))
            ->filter(fn (string $path): bool => in_array($path, $availablePaths, true))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function availablePackagePaths(): array
    {
        return collect(['mod/*/composer.json', 'ext/*/composer.json'])
            ->flatMap(function (string $pattern): array {
                $matches = glob($this->absolutePath($pattern));

                return is_array($matches) ? $matches : [];
            })
            ->map(fn (string $path): string => $this->relativePath($path))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $paths
     */
    public function writeEnabledPackagePaths(array $paths): void
    {
        $normalizedPaths = collect($paths)
            ->map(fn (string $path): string => $this->relativePath($this->absolutePath($path)))
            ->filter(fn (string $path): bool => in_array($path, $this->availablePackagePaths(), true))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $composerLocal = $this->readJson($this->composerLocalPath()) ?? [];

        $composerLocal['extra'] ??= [];
        $composerLocal['extra']['merge-plugin'] ??= [];
        $composerLocal['extra']['merge-plugin']['include'] = $normalizedPaths;

        file_put_contents(
            $this->composerLocalPath(),
            json_encode($composerLocal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );
    }

    /**
     * @return list<string>
     */
    public function resolvedMergePluginIncludes(?string $path = null): array
    {
        $path ??= $this->rootComposerPath();

        return $this->resolveIncludesForFile($path, []);
    }

    public function composerLocalPath(): string
    {
        return $this->absolutePath('composer.local.json');
    }

    public function rootComposerPath(): string
    {
        return $this->absolutePath('composer.json');
    }

    protected function absolutePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return rtrim($this->basePath, '/').'/'.ltrim($path, '/');
    }

    protected function relativePath(string $path): string
    {
        return ltrim(str_replace(rtrim($this->basePath, '/').'/', '', $path), '/');
    }

    /**
     * @param  list<string>  $visited
     * @return list<string>
     */
    protected function resolveIncludesForFile(string $path, array $visited): array
    {
        $realPath = realpath($path) ?: $path;

        if (in_array($realPath, $visited, true)) {
            return [];
        }

        $visited[] = $realPath;

        $composer = $this->readJson($realPath);

        if (! is_array($composer)) {
            return [];
        }

        $patterns = data_get($composer, 'extra.merge-plugin.include', []);

        if (! is_array($patterns)) {
            return [];
        }

        return collect($patterns)
            ->filter(fn (mixed $pattern): bool => is_string($pattern) && $pattern !== '')
            ->flatMap(function (string $pattern) use ($realPath, $visited): array {
                $matches = glob(dirname($realPath).'/'.$pattern);

                if (! is_array($matches)) {
                    return [];
                }

                return collect($matches)
                    ->flatMap(function (string $match) use ($visited): array {
                        $resolvedMatch = $this->normalizeIncludeMatch($match);

                        if ($resolvedMatch === null) {
                            return [];
                        }

                        $json = $this->readJson($resolvedMatch);

                        if (! is_array($json)) {
                            return [];
                        }

                        if (isset($json['name']) && is_string($json['name'])) {
                            return [$resolvedMatch];
                        }

                        return $this->resolveIncludesForFile($resolvedMatch, $visited);
                    })
                    ->all();
            })
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function readJson(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function normalizeIncludeMatch(string $match): ?string
    {
        if (is_dir($match)) {
            $composerPath = rtrim($match, '/').'/composer.json';

            return is_file($composerPath) ? $composerPath : null;
        }

        return is_file($match) ? $match : null;
    }
}
