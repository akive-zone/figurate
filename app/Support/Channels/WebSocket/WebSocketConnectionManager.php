<?php

namespace App\Support\Channels\WebSocket;

use App\Models\Server\Channel;
use Illuminate\Support\Str;

/**
 * WebSocket Connection Manager
 *
 * Manages persistent WebSocket client connections to remote servers.
 * Handles connection pooling, reconnection, and lifecycle management.
 */
class WebSocketConnectionManager
{
    /**
     * @var array<string, WebSocketConnection>
     */
    protected array $connections = [];

    /**
     * Get or create a WebSocket connection to a remote endpoint
     *
     * @param  array<string, mixed>  $config
     */
    public function getOrCreateConnection(Channel $channel, string $endpointUrl, array $config = []): WebSocketConnection
    {
        $connectionKey = $this->generateConnectionKey($channel, $endpointUrl);

        // Check in-memory pool first
        if (isset($this->connections[$connectionKey]) && $this->connections[$connectionKey]->isConnected()) {
            return $this->connections[$connectionKey];
        }

        // Create new connection
        $connection = $this->createConnection($channel, $endpointUrl, $config);

        // Store in pool
        $this->connections[$connectionKey] = $connection;

        return $connection;
    }

    /**
     * Create a new WebSocket connection
     *
     * @param  array<string, mixed>  $config
     */
    protected function createConnection(Channel $channel, string $endpointUrl, array $config): WebSocketConnection
    {
        $connectionId = (string) Str::uuid();

        $options = [
            'timeout' => $config['timeout'] ?? 10,
            'headers' => $this->resolveHeaders($channel, $config),
        ];

        $connection = new WebSocketConnection(
            id: $connectionId,
            url: $endpointUrl,
            options: $options,
        );

        $connection->connect();

        return $connection;
    }

    /**
     * @return array<string, string>
     */
    protected function resolveHeaders(Channel $channel, array $config): array
    {
        $channelHeaders = [];
        if (is_array($channel->credentials)) {
            $channelHeaders = is_array($channel->credentials['headers'] ?? null)
                ? $channel->credentials['headers']
                : [];
        }

        $configHeaders = data_get($config, 'credentials.headers')
            ?? data_get($config, 'config.credentials.headers')
            ?? [];

        $configHeaders = is_array($configHeaders) ? $configHeaders : [];

        return collect($channelHeaders)
            ->merge($configHeaders)
            ->filter(fn (mixed $value, mixed $key): bool => is_string($key) && is_string($value))
            ->all();
    }

    protected function generateConnectionKey(Channel $channel, string $endpointUrl): string
    {
        return md5($channel->id.':'.$endpointUrl);
    }

    /**
     * Close a specific connection
     */
    public function closeConnection(string $connectionKey): void
    {
        if (isset($this->connections[$connectionKey])) {
            $this->connections[$connectionKey]->close();
            unset($this->connections[$connectionKey]);
        }
    }

    /**
     * Close all connections
     */
    public function closeAll(): void
    {
        foreach ($this->connections as $connection) {
            $connection->close();
        }

        $this->connections = [];
    }

    /**
     * Get connection by channel and URL
     */
    public function getConnection(Channel $channel, string $endpointUrl): ?WebSocketConnection
    {
        $connectionKey = $this->generateConnectionKey($channel, $endpointUrl);

        return $this->connections[$connectionKey] ?? null;
    }

    /**
     * Check if a connection exists and is active
     */
    public function hasActiveConnection(Channel $channel, string $endpointUrl): bool
    {
        $connection = $this->getConnection($channel, $endpointUrl);

        return $connection !== null && $connection->isConnected();
    }
}
