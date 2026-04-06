<?php

namespace App\Console\Commands;

use App\Models\Server\Channel;
use App\Support\Channels\WebSocket\InboundMessageHandler;
use App\Support\Channels\WebSocket\WebSocketServer;
use Illuminate\Console\Command;
use WebSocket\Connection;

class WebSocketServe extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'websocket:serve
                            {--host=0.0.0.0 : The host to bind to}
                            {--port=8090 : The port to listen on}
                            {--channel= : The channel UUID to associate with this server}';

    /**
     * The console command description.
     */
    protected $description = 'Start WebSocket server for client → server communication';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $host = (string) $this->option('host');
        $port = (int) $this->option('port');
        $channelUuid = $this->option('channel');

        $channel = null;
        if (is_string($channelUuid) && trim($channelUuid) !== '') {
            $channel = Channel::query()->where('uuid', trim($channelUuid))->first();
            if (! $channel) {
                $this->error("Channel not found: {$channelUuid}");

                return self::FAILURE;
            }
        }

        $this->info('Starting WebSocket server...');
        $this->info("Host: {$host}");
        $this->info("Port: {$port}");

        if ($channel) {
            $this->info("Channel: {$channel->name} ({$channel->uuid})");
        }

        $this->newLine();

        $server = new WebSocketServer($host, $port, $channel);
        $handler = app(InboundMessageHandler::class);

        // Set custom message handler
        $server->onMessage(function (Connection $connection, mixed $data, string $type) use ($handler, $channel) {
            if ($type === 'text' && is_array($data)) {
                try {
                    $response = $handler->handle($connection, $data, $channel);
                    $connection->text(json_encode($response));

                    $this->info('Message processed: '.json_encode($response));
                } catch (\Throwable $e) {
                    $this->error("Error processing message: {$e->getMessage()}");

                    $connection->text(json_encode([
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ]));
                }
            }
        });

        // Start the server (blocking)
        try {
            $server->start();
        } catch (\Throwable $e) {
            $this->error("Server error: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
