<?php

namespace App\Support\Security;

class UrlTrustPolicy
{
    /**
     * @param  array<string, mixed>  $options
     * @return array{allowed: bool, reason?: string}
     */
    public function authorize(string $url, array $options = []): array
    {
        $trimmedUrl = trim($url);

        if ($trimmedUrl === '') {
            return [
                'allowed' => false,
                'reason' => 'URL is required.',
            ];
        }

        $parts = parse_url($trimmedUrl);

        if (! is_array($parts)) {
            return [
                'allowed' => false,
                'reason' => 'URL is invalid.',
            ];
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $user = (string) ($parts['user'] ?? '');
        $pass = (string) ($parts['pass'] ?? '');
        $allowHttp = $this->booleanOption($options, 'allow_http', $this->isDevelopmentEnvironment());
        $allowPrivateNetwork = $this->booleanOption($options, 'allow_private_network', $this->isDevelopmentEnvironment());
        $allowedHosts = $this->normalizeStringList($options['allowed_hosts'] ?? []);

        if (! in_array($scheme, ['https', 'http'], true)) {
            return [
                'allowed' => false,
                'reason' => 'Only http and https URLs are supported.',
            ];
        }

        if ($host === '') {
            return [
                'allowed' => false,
                'reason' => 'URL host is required.',
            ];
        }

        if ($user !== '' || $pass !== '') {
            return [
                'allowed' => false,
                'reason' => 'URLs with embedded credentials are not allowed.',
            ];
        }

        if ($scheme === 'http' && ! $allowHttp) {
            return [
                'allowed' => false,
                'reason' => 'Plain HTTP URLs are not allowed by policy.',
            ];
        }

        if ($allowedHosts !== [] && ! $this->hostMatchesAllowlist($host, $allowedHosts)) {
            return [
                'allowed' => false,
                'reason' => 'URL host is not allowlisted.',
            ];
        }

        if ($this->isPrivateOrLocalHost($host) && ! $allowPrivateNetwork) {
            return [
                'allowed' => false,
                'reason' => 'Private or local network hosts are not allowed by policy.',
            ];
        }

        return [
            'allowed' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function booleanOption(array $options, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $options)) {
            return $default;
        }

        return filter_var($options[$key], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    protected function hostMatchesAllowlist(string $host, array $allowedHosts): bool
    {
        foreach ($allowedHosts as $allowedHost) {
            $normalized = strtolower($allowedHost);

            if ($normalized === $host) {
                return true;
            }

            if (str_starts_with($normalized, '*.')) {
                $suffix = substr($normalized, 1);

                if ($suffix !== false && str_ends_with($host, $suffix)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function isPrivateOrLocalHost(string $host): bool
    {
        $normalizedHost = strtolower(trim($host));

        if ($normalizedHost === '') {
            return true;
        }

        if (
            in_array($normalizedHost, ['localhost', '127.0.0.1', '::1', 'host.docker.internal'], true)
            || str_ends_with($normalizedHost, '.localhost')
            || str_ends_with($normalizedHost, '.local')
            || str_ends_with($normalizedHost, '.internal')
        ) {
            return true;
        }

        if (! str_contains($normalizedHost, '.') && filter_var($normalizedHost, FILTER_VALIDATE_IP) === false) {
            return true;
        }

        if (filter_var($normalizedHost, FILTER_VALIDATE_IP) !== false) {
            return filter_var($normalizedHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        return false;
    }

    protected function isDevelopmentEnvironment(): bool
    {
        return app()->environment(['local', 'testing']);
    }

    /**
     * @return list<string>
     */
    protected function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $entry): bool => is_string($entry) && trim($entry) !== '')
            ->map(fn (string $entry): string => trim($entry))
            ->unique()
            ->values()
            ->all();
    }
}
