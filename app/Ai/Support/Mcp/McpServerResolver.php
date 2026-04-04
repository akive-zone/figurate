<?php

namespace App\Ai\Support\Mcp;

use App\Ai\Support\ThreadContextResolver;
use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Model;

class McpServerResolver
{
    public function __construct(
        protected ThreadContextResolver $threadContextResolver = new ThreadContextResolver,
    ) {}

    /**
     * @return array{
     *     enabled: bool,
     *     server: string,
     *     transport: string,
     *     mode: ?string,
     *     endpoint_url: ?string,
     *     handler: ?string,
     *     config: array<string, mixed>,
     *     default_timeout_ms: int,
     *     max_timeout_ms: int,
     *     tools: list<string>,
     *     headers: array<string, string>,
     *     context_source: ?string,
     *     context_server_id: ?int
     * }
     */
    public function resolve(string $server, ?Thread $thread = null, ?User $user = null): array
    {
        $serverDefaults = $this->serverDefaults($server);

        $resolved = [
            'enabled' => $this->isEnabled(),
            'server' => $server,
            'transport' => $this->stringValue(
                $serverDefaults['transport'] ?? 'http',
            ) ?? 'http',
            'mode' => $this->stringValue($serverDefaults['mode'] ?? 'remote'),
            'endpoint_url' => $this->stringValue($serverDefaults['endpoint_url'] ?? null),
            'handler' => $this->stringValue(
                $serverDefaults['handler'] ?? config('services.mcp.default_handler'),
            ),
            'config' => is_array($serverDefaults['config'] ?? null) ? $serverDefaults['config'] : [],
            'default_timeout_ms' => $this->intValue(
                $serverDefaults['default_timeout_ms'] ?? $this->defaultTimeout(),
                $this->defaultTimeout(),
            ),
            'max_timeout_ms' => $this->intValue(
                $serverDefaults['max_timeout_ms'] ?? $this->maxTimeout(),
                $this->maxTimeout(),
            ),
            'tools' => $this->normalizeStringList($serverDefaults['tools'] ?? []),
            'headers' => $this->normalizeHeaders($serverDefaults['headers'] ?? []),
            'context_source' => null,
            'context_server_id' => null,
        ];

        $contextServer = $this->resolveContextServer($server, $thread, $user);

        if (! $contextServer) {
            return $resolved;
        }

        $contextConnection = $this->resolveContextConnection($contextServer);
        $resolved['context_server_id'] = $contextServer->id;
        $resolved['context_source'] = $this->contextSource($contextServer);

        $contextMeta = is_array($contextServer->meta) ? $contextServer->meta : [];
        $connectionConfig = is_array($contextConnection?->config ?? null) ? $contextConnection->config : [];
        $transport = $this->stringValue($connectionConfig['transport'] ?? $contextServer->transport ?? null);
        if ($transport !== null) {
            $resolved['transport'] = strtolower($transport);
        }

        $mode = $this->stringValue($connectionConfig['mode'] ?? $contextMeta['mode'] ?? null);
        if ($mode !== null) {
            $resolved['mode'] = strtolower($mode);
        }

        $endpointUrl = $this->stringValue($connectionConfig['endpoint_url'] ?? $contextServer->endpoint_url ?? null);
        if ($endpointUrl !== null) {
            $resolved['endpoint_url'] = $endpointUrl;
        }

        $handler = $this->stringValue($connectionConfig['handler'] ?? $contextMeta['handler'] ?? $contextServer->handler ?? null);
        if ($handler !== null) {
            $resolved['handler'] = $handler;
        }

        $mergedConfig = $connectionConfig !== []
            ? $connectionConfig
            : (is_array($contextServer->config ?? null) ? $contextServer->config : []);
        if ($mergedConfig !== []) {
            $resolved['config'] = array_merge($resolved['config'], $mergedConfig);
        }

        $allowedTools = $this->normalizeStringList($contextServer->allowed_tools ?? []);
        if ($allowedTools !== []) {
            $resolved['tools'] = $allowedTools;
        }

        $resolved['headers'] = array_merge(
            $resolved['headers'],
            $this->headersFromContextServer($contextServer),
        );

        return $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serverDefaults(string $server): array
    {
        $servicesDefaults = config("services.mcp.servers.{$server}", []);

        return is_array($servicesDefaults) ? $servicesDefaults : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function available(?Thread $thread = null, ?User $user = null): array
    {
        $serverNames = collect(array_keys((array) config('services.mcp.servers', [])))
            ->values();

        foreach ($this->credentialCandidates($thread, $user) as $credentialable) {
            if (! method_exists($credentialable, 'contextServers')) {
                continue;
            }

            $serverNames = $serverNames->merge(
                $credentialable->contextServers()
                    ->where('enabled', true)
                    ->pluck('server')
                    ->all()
            );
        }

        return $serverNames
            ->filter(fn (mixed $name): bool => is_string($name) && trim($name) !== '')
            ->map(fn (string $name): array => $this->resolve(trim($name), $thread, $user))
            ->unique('server')
            ->values()
            ->all();
    }

    protected function isEnabled(): bool
    {
        return (bool) (
            config('services.mcp.enabled', false)
        );
    }

    protected function defaultTimeout(): int
    {
        return $this->intValue(
            config('services.mcp.default_timeout_ms', 8000),
            8000,
        );
    }

    protected function maxTimeout(): int
    {
        return $this->intValue(
            config('services.mcp.max_timeout_ms', 30000),
            30000,
        );
    }

    protected function resolveContextServer(string $server, ?Thread $thread, ?User $user): ?Channel
    {
        foreach ($this->credentialCandidates($thread, $user) as $credentialable) {
            $contextServer = $this->findContextServerFor($credentialable, $server);
            if ($contextServer) {
                return $contextServer;
            }
        }

        return null;
    }

    /**
     * @return list<Model>
     */
    protected function credentialCandidates(?Thread $thread, ?User $user): array
    {
        $candidates = [];

        if ($thread) {
            $candidates[] = $thread;

            $space = $this->threadContextResolver->resolveSpace($thread);
            if ($space) {
                $candidates[] = $space;
            }
        }

        if ($user) {
            $candidates[] = $user;
        }

        return $candidates;
    }

    protected function findContextServerFor(Model $owner, string $server): ?Channel
    {
        if (! method_exists($owner, 'contextServers')) {
            return null;
        }

        return $owner->contextServers()
            ->where('server', $server)
            ->where('driver', Channel::DriverMcp)
            ->where('enabled', true)
            ->wherePivot('status', Channel::StatusActive)
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->first();
    }

    protected function contextSource(Channel $contextServer): ?string
    {
        $sourceType = $contextServer->relations()
            ->where('kind', ChannelRelation::KindLink)
            ->orderByDesc('id')
            ->value('relationable_type');

        if (! is_string($sourceType) || $sourceType === '') {
            return null;
        }

        return class_basename($sourceType);
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

    /**
     * @return array<string, string>
     */
    protected function normalizeHeaders(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $headers = [];

        foreach ($value as $key => $headerValue) {
            if (! is_string($key) || trim($key) === '' || ! is_string($headerValue)) {
                continue;
            }

            $headers[trim($key)] = $headerValue;
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    protected function headersFromContextServer(Channel $contextServer): array
    {
        $headers = [];
        $contextConnection = $this->resolveContextConnection($contextServer);
        $connectionConfig = is_array($contextConnection?->config ?? null) ? $contextConnection->config : [];
        $credentials = is_array($connectionConfig['credentials'] ?? null)
            ? $connectionConfig['credentials']
            : (is_array($contextServer->credentials) ? $contextServer->credentials : []);

        $authType = is_string($connectionConfig['auth_type'] ?? null)
            ? strtolower(trim((string) $connectionConfig['auth_type']))
            : (is_string($contextServer->auth_type) ? strtolower(trim($contextServer->auth_type)) : '');

        if ($authType === 'bearer') {
            $token = is_string($credentials['token'] ?? null) ? trim($credentials['token']) : '';
            if ($token !== '') {
                $headers['Authorization'] = "Bearer {$token}";
            }
        }

        if ($authType === 'basic') {
            $username = is_string($credentials['username'] ?? null) ? $credentials['username'] : '';
            $password = is_string($credentials['password'] ?? null) ? $credentials['password'] : '';

            if ($username !== '' || $password !== '') {
                $headers['Authorization'] = 'Basic '.base64_encode("{$username}:{$password}");
            }
        }

        if ($authType === 'header') {
            $headerName = is_string($credentials['header_name'] ?? null) ? trim($credentials['header_name']) : '';
            $headerValue = is_string($credentials['header_value'] ?? null) ? $credentials['header_value'] : '';

            if ($headerName !== '' && $headerValue !== '') {
                $headers[$headerName] = $headerValue;
            }
        }

        $extraHeaders = $this->normalizeHeaders($credentials['headers'] ?? []);

        return array_merge($headers, $extraHeaders);
    }

    protected function resolveContextConnection(Channel $contextServer): ?ChannelRelation
    {
        $connectionId = data_get($contextServer, 'pivot.id');

        if (! is_numeric($connectionId)) {
            return null;
        }

        return ChannelRelation::query()->find((int) $connectionId);
    }

    protected function intValue(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    protected function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
