<?php

namespace App\Support\Channels\Transports;

use App\Models\Server\Channel;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use Illuminate\Support\Facades\Broadcast;

/**
 * WebSocket Server Transport - Broadcasts messages to connected clients via Laravel Reverb
 *
 * Direction: Outbound (Figurate → Connected WS Clients)
 * Mode: Server (Figurate hosts the WebSocket server)
 */
class WebSocketServerTransport
{
    /**
     * @param  array<string, mixed>  $bindingConfig
     * @return array<string, mixed>
     */
    public function deliver(Channel $channel, Thread $thread, Post $message, array $bindingConfig = []): array
    {
        $outboundPayload = is_array($bindingConfig['outbound_payload'] ?? null)
            ? $bindingConfig['outbound_payload']
            : [];

        $channelName = $this->resolveChannelName($channel, $thread, $bindingConfig);

        if ($channelName === null || $channelName === '') {
            throw new \RuntimeException('WebSocket channel name is required for broadcast delivery.');
        }

        // Broadcast to the channel using Laravel Broadcasting
        Broadcast::channel($channelName)->send($outboundPayload);

        return [
            'status' => 'broadcast',
            'provider' => $channel->driver,
            'provider_message_id' => $this->generateMessageId($channel, $thread, $message),
            'provider_identifier' => $bindingConfig['provider_identifier'] ?? null,
            'thread_uuid' => $thread->uuid,
            'message_id' => $message->id,
            'channel_name' => $channelName,
            'transport' => 'websocket-server',
            'mode' => 'server',
            'payload' => $outboundPayload,
        ];
    }

    protected function resolveChannelName(Channel $channel, Thread $thread, array $bindingConfig): ?string
    {
        // Check binding config first
        $bindingChannel = data_get($bindingConfig, 'delivery.binding.config.channel_name')
            ?? data_get($bindingConfig, 'config.channel_name')
            ?? data_get($bindingConfig, 'channel_name');

        if (is_string($bindingChannel) && trim($bindingChannel) !== '') {
            return trim($bindingChannel);
        }

        // Check channel handler (could be the broadcast channel name)
        if (is_string($channel->handler) && trim($channel->handler) !== '') {
            return trim($channel->handler);
        }

        // Default: Use thread UUID as channel name
        return "thread.{$thread->uuid}";
    }

    protected function generateMessageId(Channel $channel, Thread $thread, Post $message): string
    {
        return sprintf(
            'ws-server:%s:%s:%s',
            $channel->uuid,
            $thread->uuid,
            $message->ulid
        );
    }
}
