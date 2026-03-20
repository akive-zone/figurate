<?php

namespace App\Ai\Support\A2a;

use App\Support\Security\UrlTrustPolicy;

class OutboundAgentRegistry
{
    public function __construct(
        protected UrlTrustPolicy $urlTrustPolicy = new UrlTrustPolicy,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('a2a.outbound.enabled', false);
    }

    public function defaultTimeoutSeconds(): int
    {
        $timeout = (int) config('a2a.outbound.default_timeout_seconds', 15);

        return max(3, min(120, $timeout));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function agents(): array
    {
        $configuredAgents = config('a2a.outbound.agents', []);

        if (! is_array($configuredAgents)) {
            return [];
        }

        return collect($configuredAgents)
            ->map(function (mixed $agentConfig, mixed $key): ?array {
                if (! is_array($agentConfig)) {
                    return null;
                }

                $configuredId = is_string($agentConfig['id'] ?? null)
                    ? trim((string) $agentConfig['id'])
                    : null;
                $id = $configuredId !== '' && $configuredId !== null
                    ? $configuredId
                    : (is_string($key) ? trim($key) : '');

                if ($id === '') {
                    return null;
                }

                $endpoint = is_string($agentConfig['endpoint'] ?? null)
                    ? trim((string) $agentConfig['endpoint'])
                    : '';

                if ($endpoint === '') {
                    return null;
                }

                return [
                    'id' => $id,
                    'label' => $this->stringOrNull($agentConfig['label'] ?? null),
                    'endpoint' => $endpoint,
                    'auth_type' => strtolower((string) ($agentConfig['auth_type'] ?? 'none')),
                    'token' => $this->stringOrNull($agentConfig['token'] ?? null),
                    'headers' => $this->normalizeHeaders($agentConfig['headers'] ?? []),
                    'allowed_methods' => $this->normalizeMethods($agentConfig['allowed_methods'] ?? []),
                ];
            })
            ->filter(fn (mixed $agent): bool => is_array($agent))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $agentId): ?array
    {
        $normalizedId = trim($agentId);

        if ($normalizedId === '') {
            return null;
        }

        return collect($this->agents())
            ->first(fn (array $agent): bool => (string) ($agent['id'] ?? '') === $normalizedId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function trustedAgents(): array
    {
        return collect($this->agents())
            ->filter(fn (array $agent): bool => ($this->trustDecision($agent)['allowed'] ?? false) === true)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $agent
     * @return array{allowed: bool, reason?: string}
     */
    public function trustDecision(array $agent): array
    {
        $endpoint = $this->stringOrNull($agent['endpoint'] ?? null);

        if ($endpoint === null) {
            return [
                'allowed' => false,
                'reason' => 'Remote A2A agent endpoint URL is missing.',
            ];
        }

        return $this->urlTrustPolicy->authorize(
            $endpoint,
            is_array(config('a2a.outbound.trust')) ? config('a2a.outbound.trust') : [],
        );
    }

    /**
     * @param  array<string, mixed>  $agent
     */
    public function isMethodAllowed(array $agent, string $method): bool
    {
        $allowedMethods = is_array($agent['allowed_methods'] ?? null) ? $agent['allowed_methods'] : [];

        if ($allowedMethods === []) {
            return false;
        }

        return in_array(trim($method), $allowedMethods, true);
    }

    protected function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<string, string>
     */
    protected function normalizeHeaders(mixed $headers): array
    {
        if (! is_array($headers)) {
            return [];
        }

        $normalized = [];

        foreach ($headers as $key => $value) {
            if (! is_string($key) || trim($key) === '' || ! is_string($value)) {
                continue;
            }

            $normalized[trim($key)] = $value;
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    protected function normalizeMethods(mixed $methods): array
    {
        if (! is_array($methods)) {
            return [];
        }

        return collect($methods)
            ->filter(fn (mixed $method): bool => is_string($method) && trim($method) !== '')
            ->map(fn (string $method): string => trim($method))
            ->unique()
            ->values()
            ->all();
    }
}
