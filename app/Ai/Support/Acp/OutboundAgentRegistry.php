<?php

namespace App\Ai\Support\Acp;

class OutboundAgentRegistry
{
    public function enabled(): bool
    {
        return (bool) config('acp.outbound.enabled', false);
    }

    public function defaultTimeoutSeconds(): int
    {
        $timeout = (int) config('acp.outbound.default_timeout_seconds', 20);

        return max(3, min(180, $timeout));
    }

    /**
     * @return array{id: string|null, name: string|null, version: string|null, capabilities: array<string, mixed>}
     */
    public function client(): array
    {
        $client = config('acp.outbound.client', []);

        return [
            'id' => $this->stringOrNull(data_get($client, 'id')),
            'name' => $this->stringOrNull(data_get($client, 'name')),
            'version' => $this->stringOrNull(data_get($client, 'version')),
            'capabilities' => is_array(data_get($client, 'capabilities')) ? data_get($client, 'capabilities') : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function agents(): array
    {
        $configuredAgents = config('acp.outbound.agents', []);

        if (! is_array($configuredAgents)) {
            return [];
        }

        return collect($configuredAgents)
            ->map(function (mixed $agentConfig, mixed $key): ?array {
                if (! is_array($agentConfig)) {
                    return null;
                }

                $configuredId = $this->stringOrNull($agentConfig['id'] ?? null);
                $id = $configuredId ?? (is_string($key) ? trim($key) : '');

                if ($id === '') {
                    return null;
                }

                $endpoint = $this->stringOrNull($agentConfig['endpoint'] ?? null);

                if ($endpoint === null) {
                    return null;
                }

                return [
                    'id' => $id,
                    'label' => $this->stringOrNull($agentConfig['label'] ?? null),
                    'endpoint' => $endpoint,
                    'transport' => $this->stringOrNull($agentConfig['transport'] ?? null) ?? 'jsonrpc-http',
                    'gateway_agent' => $this->stringOrNull($agentConfig['gateway_agent'] ?? null),
                    'auth_type' => strtolower((string) ($agentConfig['auth_type'] ?? 'none')),
                    'token' => $this->stringOrNull($agentConfig['token'] ?? null),
                    'headers' => $this->normalizeHeaders($agentConfig['headers'] ?? []),
                    'allowed_methods' => $this->normalizeMethods($agentConfig['allowed_methods'] ?? []),
                    'initialize_payload' => is_array($agentConfig['initialize_payload'] ?? null) ? $agentConfig['initialize_payload'] : [],
                    'authenticate_payload' => is_array($agentConfig['authenticate_payload'] ?? null) ? $agentConfig['authenticate_payload'] : [],
                    'session' => $this->normalizeSessionConfig($agentConfig['session'] ?? []),
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

    /**
     * @return array{
     *     reuse: string,
     *     create_method: string,
     *     load_method: string,
     *     prompt_method: string,
     *     id_argument: string,
     *     prompt_argument: string,
     *     prompt_mode: string,
     *     load_after_prompt: bool,
     *     create_params: array<string, mixed>,
     *     load_params: array<string, mixed>,
     *     prompt_params: array<string, mixed>
     * }
     */
    protected function normalizeSessionConfig(mixed $config): array
    {
        $config = is_array($config) ? $config : [];

        return [
            'reuse' => $this->stringOrNull($config['reuse'] ?? null) ?? 'thread',
            'create_method' => $this->stringOrNull($config['create_method'] ?? null) ?? 'session/new',
            'load_method' => $this->stringOrNull($config['load_method'] ?? null) ?? 'session/load',
            'prompt_method' => $this->stringOrNull($config['prompt_method'] ?? null) ?? 'session/prompt',
            'id_argument' => $this->stringOrNull($config['id_argument'] ?? null) ?? 'session_id',
            'prompt_argument' => $this->stringOrNull($config['prompt_argument'] ?? null) ?? 'prompt',
            'prompt_mode' => $this->stringOrNull($config['prompt_mode'] ?? null) ?? 'string',
            'load_after_prompt' => (bool) ($config['load_after_prompt'] ?? true),
            'create_params' => is_array($config['create_params'] ?? null) ? $config['create_params'] : [],
            'load_params' => is_array($config['load_params'] ?? null) ? $config['load_params'] : [],
            'prompt_params' => is_array($config['prompt_params'] ?? null) ? $config['prompt_params'] : [],
        ];
    }
}
