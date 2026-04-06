<?php

namespace App\Support\Channels\WebSocket;

use WebSocket\Client;
use WebSocket\Connection;
use WebSocket\Middleware\CloseHandler;
use WebSocket\Middleware\PingResponder;

/**
 * WebSocket Connection
 *
 * Wrapper around phrity/websocket client connection.
 * Handles connection lifecycle, reconnection, and message delivery.
 *
 * @see https://phrity.sirn.se/websocket
 */
class WebSocketConnection
{
    protected ?Client $socket = null;

    protected bool $connected = false;

    protected int $reconnectAttempts = 0;

    protected int $maxReconnectAttempts = 5;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        protected string $id,
        protected string $url,
        protected array $options = [],
    ) {}

    public function connect(): void
    {
        if ($this->connected && $this->socket) {
            return;
        }

        try {
            $this->socket = new Client($this->url, $this->options);

            // Add standard middlewares
            $this->socket
                ->addMiddleware(new CloseHandler)
                ->addMiddleware(new PingResponder);

            $this->connected = true;
            $this->reconnectAttempts = 0;
        } catch (\Throwable $e) {
            $this->connected = false;
            throw new \RuntimeException("Failed to connect to WebSocket: {$e->getMessage()}", 0, $e);
        }
    }

    public function send(string $message): void
    {
        if (! $this->connected || ! $this->socket) {
            throw new \RuntimeException('WebSocket connection is not established.');
        }

        try {
            $this->socket->text($message);
        } catch (\Throwable $e) {
            $this->connected = false;
            throw new \RuntimeException("Failed to send WebSocket message: {$e->getMessage()}", 0, $e);
        }
    }

    public function receive(?int $timeout = null): ?string
    {
        if (! $this->connected || ! $this->socket) {
            return null;
        }

        try {
            $message = $this->socket->receive();

            if ($message) {
                return (string) $message->getContent();
            }

            return null;
        } catch (\Throwable $e) {
            $this->connected = false;

            return null;
        }
    }

    public function close(): void
    {
        if (! $this->connected || ! $this->socket) {
            return;
        }

        try {
            $this->socket->close();
        } catch (\Throwable $e) {
            // Ignore close errors
        } finally {
            $this->connected = false;
            $this->socket = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->connected && $this->socket !== null;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Reconnect if disconnected with exponential backoff
     */
    public function ensureConnected(): void
    {
        if ($this->connected) {
            return;
        }

        $this->reconnectWithBackoff();
    }

    protected function reconnectWithBackoff(): void
    {
        while ($this->reconnectAttempts < $this->maxReconnectAttempts) {
            try {
                $this->reconnectAttempts++;

                // Exponential backoff: 1s, 2s, 4s, 8s, 16s
                $delay = min(2 ** ($this->reconnectAttempts - 1), 16);
                sleep($delay);

                $this->connect();

                return;
            } catch (\Throwable $e) {
                if ($this->reconnectAttempts >= $this->maxReconnectAttempts) {
                    throw new \RuntimeException(
                        "Failed to reconnect after {$this->maxReconnectAttempts} attempts: {$e->getMessage()}",
                        0,
                        $e
                    );
                }
            }
        }
    }

    /**
     * Register a callback for incoming text messages
     */
    public function onText(callable $callback): self
    {
        if ($this->socket) {
            $this->socket->onText($callback);
        }

        return $this;
    }

    /**
     * Register a callback for incoming binary messages
     */
    public function onBinary(callable $callback): self
    {
        if ($this->socket) {
            $this->socket->onBinary($callback);
        }

        return $this;
    }

    /**
     * Get the underlying WebSocket client
     */
    public function getClient(): ?Client
    {
        return $this->socket;
    }
}
