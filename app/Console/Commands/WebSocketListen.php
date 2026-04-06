<?php

namespace App\Console\Commands;

use App\Models\Server\Channel;
use App\Support\Channels\WebSocket\InboundClientMessageHandler;
use App\Support\Channels\WebSocket\WebSocketConnectionManager;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use WebSocket\Client;
use WebSocket\Message\Message;

class WebSocketListen extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'websocket:listen
                            {--channel= : The channel UUID to listen on (required unless --all is used)}
                            {--all : Listen on all active WebSocket channels simultaneously}
                            {--restart-delay=5 : Seconds to wait before restarting a failed listener (only with --all)}';

    /**
     * The console command description.
     */
    protected $description = 'Listen for incoming messages from external WebSocket servers (client mode)';

    /**
     * @var array<string, Process>
     */
    protected array $processes = [];

    /**
     * @var array<string, int>
     */
    protected array $restartCounts = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Check if --all flag is used
        if ($this->option('all')) {
            return $this->handleMultiChannel();
        }

        return $this->handleSingleChannel();
    }

    /**
     * Handle listening to a single channel
     */
    protected function handleSingleChannel(): int
    {
        $channelUuid = $this->option('channel');

        if (! is_string($channelUuid) || trim($channelUuid) === '') {
            $this->error('Channel UUID is required. Use --channel=UUID or --all to listen on all channels.');

            return self::FAILURE;
        }

        $channel = Channel::query()->where('uuid', trim($channelUuid))->first();
        if (! $channel) {
            $this->error("Channel not found: {$channelUuid}");

            return self::FAILURE;
        }

        if ($channel->transportKey() !== Channel::TransportWebsocket) {
            $this->error('Channel must use the websocket transport.');

            return self::FAILURE;
        }

        $endpointUrl = $channel->endpoint_url;
        if (! is_string($endpointUrl) || trim($endpointUrl) === '') {
            $this->error('Channel must have an endpoint_url configured.');

            return self::FAILURE;
        }

        $this->info('Starting WebSocket client listener...');
        $this->info("Channel: {$channel->name} ({$channel->uuid})");
        $this->info("Connecting to: {$endpointUrl}");
        $this->newLine();

        $handler = app(InboundClientMessageHandler::class);

        try {
            // Create connection using the connection manager
            $connectionManager = app(WebSocketConnectionManager::class);
            $config = is_array($channel->config) ? $channel->config : [];
            $connection = $connectionManager->getOrCreateConnection($channel, $endpointUrl, $config);

            $this->info('✓ Connected successfully!');
            $this->info('Listening for messages... (Press Ctrl+C to stop)');
            $this->newLine();

            // Get the underlying client for listening
            $client = $connection->getClient();

            if (! $client) {
                $this->error('Failed to get WebSocket client.');

                return self::FAILURE;
            }

            // Listen loop - blocking operation
            while (true) {
                try {
                    $message = $client->receive();

                    if ($message instanceof Message) {
                        $content = $message->getContent();
                        $opcode = $message->getOpcode();

                        if ($opcode === 'text') {
                            $this->handleTextMessage($channel, $content, $handler);
                        } elseif ($opcode === 'binary') {
                            $this->info('[BINARY] Received binary message (not processed)');
                        } elseif ($opcode === 'close') {
                            $this->warn('WebSocket connection closed by server.');
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    $this->error("Error receiving message: {$e->getMessage()}");

                    // Try to reconnect
                    $this->warn('Attempting to reconnect...');
                    sleep(5);

                    try {
                        $connection = $connectionManager->getOrCreateConnection($channel, $endpointUrl, $config);
                        $client = $connection->getClient();
                        $this->info('✓ Reconnected successfully!');
                    } catch (\Throwable $reconnectError) {
                        $this->error("Failed to reconnect: {$reconnectError->getMessage()}");

                        return self::FAILURE;
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->error("Connection error: {$e->getMessage()}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Handle listening to all channels simultaneously
     */
    protected function handleMultiChannel(): int
    {
        $channels = $this->getListenableChannels();

        if ($channels->isEmpty()) {
            $this->warn('No WebSocket channels found that require listening.');
            $this->info('Channels must have:');
            $this->info('  - transport = "websocket"');
            $this->info('  - endpoint_url configured');
            $this->info('  - enabled = true');
            $this->info('  - direction = "inbound" or "bidirectional"');

            return self::SUCCESS;
        }

        $this->info('WebSocket Multi-Channel Listener Manager');
        $this->info('========================================');
        $this->newLine();
        $this->info("Found {$channels->count()} channel(s) to monitor:");

        foreach ($channels as $channel) {
            $this->line("  - {$channel->name} ({$channel->uuid})");
            $this->line("    → {$channel->endpoint_url}");
        }

        $this->newLine();
        $this->info('Starting listeners... (Press Ctrl+C to stop all)');
        $this->newLine();

        // Start a process for each channel
        foreach ($channels as $channel) {
            $this->startListener($channel);
        }

        // Monitor loop
        while (true) {
            sleep(1);

            foreach ($this->processes as $channelUuid => $process) {
                if (! $process->isRunning()) {
                    $channel = $channels->firstWhere('uuid', $channelUuid);

                    if (! $channel) {
                        continue;
                    }

                    $exitCode = $process->getExitCode();
                    $this->warn("Listener for '{$channel->name}' stopped (exit code: {$exitCode})");

                    // Get restart delay
                    $restartDelay = (int) $this->option('restart-delay');

                    if ($restartDelay > 0) {
                        $this->info("Restarting in {$restartDelay} seconds...");
                        sleep($restartDelay);
                    }

                    // Restart the listener
                    $this->restartListener($channel);
                }
            }
        }

        return self::SUCCESS;
    }

    protected function startListener(Channel $channel): void
    {
        $command = [
            PHP_BINARY,
            'artisan',
            'websocket:listen',
            "--channel={$channel->uuid}",
        ];

        $process = new Process(
            command: $command,
            cwd: base_path(),
            timeout: null
        );

        $process->start(function ($type, $buffer): void {
            // Forward output from child processes
            if ($type === Process::ERR) {
                $this->error($buffer);
            } else {
                $this->line($buffer);
            }
        });

        $this->processes[$channel->uuid] = $process;
        $this->restartCounts[$channel->uuid] = 0;

        $this->info("✓ Started listener for: {$channel->name}");
    }

    protected function restartListener(Channel $channel): void
    {
        // Increment restart count
        $this->restartCounts[$channel->uuid] = ($this->restartCounts[$channel->uuid] ?? 0) + 1;
        $restartCount = $this->restartCounts[$channel->uuid];

        $this->info("↻ Restarting listener for: {$channel->name} (restart #{$restartCount})");

        // Clean up old process
        if (isset($this->processes[$channel->uuid])) {
            unset($this->processes[$channel->uuid]);
        }

        // Start new process
        $this->startListener($channel);
    }

    /**
     * Get all channels that should be listened to
     */
    protected function getListenableChannels()
    {
        return Channel::query()
            ->where('enabled', true)
            ->where('transport', Channel::TransportWebsocket)
            ->whereNotNull('endpoint_url')
            ->whereIn('direction', [Channel::DirectionInbound, Channel::DirectionBidirectional])
            ->orderBy('priority', 'desc')
            ->orderBy('name')
            ->get();
    }

    protected function handleTextMessage(Channel $channel, string $content, InboundClientMessageHandler $handler): void
    {
        $this->line("[TEXT] Received: {$content}");

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($data)) {
                $this->warn('Message is not a valid JSON object.');

                return;
            }

            $result = $handler->handle($channel, $data);

            if ($result['status'] === 'success') {
                $this->info("✓ Post created: {$result['post_ulid']} in thread {$result['thread_uuid']}");
            } else {
                $this->warn("✗ Error: {$result['error']}");
            }
        } catch (\JsonException $e) {
            $this->warn("Invalid JSON: {$e->getMessage()}");
        } catch (\Throwable $e) {
            $this->error("Processing error: {$e->getMessage()}");
        }
    }

    /**
     * Clean up processes on shutdown
     */
    public function __destruct()
    {
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
    }
}
