<?php

namespace App\Support\Runtime;

use Closure;

class AppRuntime
{
    /**
     * @var array<int, array{host: string, resolver: Closure(self): bool}>
     */
    protected array $hostResolvers = [];

    /**
     * @var array<string, string>
     */
    protected array $rootViews = [];

    protected ?string $forcedHost = null;

    protected ?bool $forcedNative = null;

    protected ?string $claimedNativeHost = null;

    public function detectHostUsing(string $host, Closure $resolver, bool $prepend = false): void
    {
        $definition = [
            'host' => $host,
            'resolver' => $resolver,
        ];

        if ($prepend) {
            array_unshift($this->hostResolvers, $definition);

            return;
        }

        $this->hostResolvers[] = $definition;
    }

    public function forceHost(string $host, ?bool $native = null): void
    {
        $this->forcedHost = $host;
        $this->forcedNative = $native;
    }

    public function registerRootView(string $host, string $view): void
    {
        $this->rootViews[$host] = $view;
    }

    public function claimNativeHost(string $host): void
    {
        $this->claimedNativeHost = $host;
    }

    public function host(): string
    {
        if ($this->forcedHost !== null) {
            return $this->forcedHost;
        }

        foreach ($this->hostResolvers as $hostResolver) {
            if (($hostResolver['resolver'])($this) === true) {
                return $hostResolver['host'];
            }
        }

        if (! $this->nativeSignal()) {
            return 'server';
        }

        return $this->claimedNativeHost ?? 'native';
    }

    public function is(string $host): bool
    {
        return $this->host() === $host;
    }

    public function isNative(): bool
    {
        if ($this->forcedNative !== null) {
            return $this->forcedNative;
        }

        return $this->nativeSignal();
    }

    public function rootView(string $default): string
    {
        return $this->rootViews[$this->host()] ?? $default;
    }

    public function nativeSignal(): bool
    {
        $nativeRuntimeFlag = $_ENV['NATIVEPHP_RUNNING'] ?? $_SERVER['NATIVEPHP_RUNNING'] ?? getenv('NATIVEPHP_RUNNING');

        if (is_bool($nativeRuntimeFlag)) {
            return $nativeRuntimeFlag;
        }

        if (is_string($nativeRuntimeFlag)) {
            return in_array(strtolower($nativeRuntimeFlag), ['1', 'true', 'yes', 'on'], true);
        }

        if (function_exists('config') && (bool) config('nativephp-internal.running')) {
            return true;
        }

        return false;
    }
}
