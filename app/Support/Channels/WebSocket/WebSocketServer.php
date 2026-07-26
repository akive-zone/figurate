<?php

namespace App\Support\Channels\WebSocket;

use App\Models\Server\Channel;
use WebSocket\Connection;
use WebSocket\Message\Message;
use WebSocket\Middleware\CloseHandler;
use WebSocket\Middleware\PingResponder;
use WebSocket\Server;

/**
 * WebSocket Server
 *
 * Accepts incoming WebSocket connections from clients and processes messages.
 * This is separate from Laravel Reverb (which handles broadcasting).
 *
 * Use Case: Client → Server communication (inbound messages)
 *
 * @see https://phrity.sirn.se/websocket
 * @see https://github.com/sirn-se/websocket-php
 */
class WebSocketServer
{
    protected ?Server $server = null;

    /**
     * @var array<string, Connection>
     */
    protected array $connections = [];

    /**
     * @var callable|null
     */
    protected $messageHandler = null;

    public function __construct(
        protected string $host = '0.0.0.0',
        protected int $port = 8090,
        protected ?Channel $channel = null,
    ) {}

    public function start(): void
    {
        $this->server = new Server($this->host, $this->port);

        // Add standard middlewares
        $this->server
            ->addMiddleware(new CloseHandler)
            ->addMiddleware(new PingResponder);

        // Handle incoming text messages
        $this->server->onText(function (Server $server, Connection $connection, Message $message) {
            $this->handleTextMessage($connection, $message);
        });

        // Handle incoming binary messages
        $this->server->onBinary(function (Server $server, Connection $connection, Message $message) {
            $this->handleBinaryMessage($connection, $message);
        });

        echo "WebSocket server listening on {$this->host}:{$this->port}\n";

        // Start the server (blocking)
        $this->server->start();
    }

    protected function handleTextMessage(Connection $connection, Message $message): void
    {
        $content = $message->getContent();

        echo "[TEXT] Received: {$content}\n";

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if (is_callable($this->messageHandler)) {
                ($this->messageHandler)($connection, $data, 'text');
            } else {
                // Default response
                $response = $this->processInboundMessage($data);
                $connection->text(json_encode($response));
            }
        } catch (\JsonException $e) {
            // Invalid JSON - send error response
            $connection->text(json_encode([
                'error' => 'Invalid JSON',
                'message' => $e->getMessage(),
            ]));
        } catch (\Throwable $e) {
            // General error - send error response
            $connection->text(json_encode([
                'error' => 'Server error',
                'message' => $e->getMessage(),
            ]));
        }
    }

    protected function handleBinaryMessage(Connection $connection, Message $message): void
    {
        echo "[BINARY] Received binary message\n";

        if (is_callable($this->messageHandler)) {
            ($this->messageHandler)($connection, $message->getContent(), 'binary');
        }
    }

    /**
     * Process inbound message and return response
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function processInboundMessage(array $data): array
    {
        // This is a placeholder - actual implementation would:
        // 1. Validate message format
        // 2. Extract thread/space identifiers
        // 3. Create Post record
        // 4. Trigger workflows
        // 5. Return acknowledgment

        return [
            'status' => 'received',
            'external_message_id' => $data['id'] ?? null,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Set custom message handler
     */
    public function onMessage(callable $handler): self
    {
        $this->messageHandler = $handler;

        return $this;
    }

    /**
     * Broadcast to all connected clients
     *
     * @param  array<string, mixed>  $data
     */
    public function broadcast(array $data): void
    {
        $json = json_encode($data);

        foreach ($this->connections as $connection) {
            try {
                $connection->text($json);
            } catch (\Throwable $e) {
                // Connection failed - will be cleaned up by middleware
            }
        }
    }

    /**
     * Send message to specific connection
     *
     * @param  array<string, mixed>  $data
     */
    public function sendToConnection(Connection $connection, array $data): void
    {
        $connection->text(json_encode($data));
    }

    public function getServer(): ?Server
    {
        return $this->server;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
        return $this->port;
    }
}
