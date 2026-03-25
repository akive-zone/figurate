<?php

namespace App\Features\Actions\Conversation;

use App\Features\Actions\Conversation\Contracts\OutboundMessageSender;
use App\Features\Actions\Conversation\Protocols\ChannelProtocol;
use App\Models\Server\Channel;
use App\Models\Server\Message;
use App\Models\Server\Outbox;
use App\Models\Server\Thread;
use App\Support\Channels\ChannelDriverRegistry;

class ChannelOutboundMessageSender implements OutboundMessageSender
{
    public function __construct(protected ChannelDriverRegistry $channelDriverRegistry) {}

    /**
     * @return array<string, mixed>
     */
    public function send(Outbox $outbox): array
    {
        $thread = $this->resolveThread($outbox);
        $message = $this->resolveMessage($outbox);
        $channel = $this->resolveChannel($outbox);

        if (
            $message->messageable_type !== $thread->getMorphClass() ||
            $message->messageable_id !== $thread->getKey()
        ) {
            throw new \RuntimeException('Channel outbox references mismatched thread resources.');
        }

        $binding = data_get($outbox->payload, 'delivery.binding');
        $bindingConfig = is_array($binding) ? $binding : [];
        $result = $this->channelDriverRegistry
            ->resolveByChannel($channel)
            ->send($channel, $thread, $message, $bindingConfig);

        return [
            'ok' => true,
            'protocol' => ChannelProtocol::Key,
            'provider' => $outbox->provider ?: $channel->driver,
            'target' => $outbox->target,
            'delivery' => data_get($result, 'status', 'queued'),
            'thread_id' => $thread->id,
            'message_id' => $message->id,
            'channel' => [
                'id' => $channel->id,
                'uuid' => $channel->uuid,
                'driver' => $channel->driver,
            ],
            'channel_result' => $result,
        ];
    }

    protected function resolveThread(Outbox $outbox): Thread
    {
        $thread = $outbox->relationLoaded('thread') ? $outbox->thread : null;
        $thread ??= Thread::query()->find($outbox->thread_id);

        if (! $thread instanceof Thread) {
            throw new \RuntimeException('Channel outbox thread could not be resolved.');
        }

        return $thread;
    }

    protected function resolveMessage(Outbox $outbox): Message
    {
        $message = $outbox->relationLoaded('message') ? $outbox->message : null;
        $message ??= $outbox->message_id ? Message::query()->find($outbox->message_id) : null;

        if (! $message instanceof Message) {
            throw new \RuntimeException('Channel outbox message could not be resolved.');
        }

        return $message;
    }

    protected function resolveChannel(Outbox $outbox): Channel
    {
        $channelUuid = data_get($outbox->payload, 'delivery.channel.uuid');
        $channel = is_string($channelUuid)
            ? Channel::query()->where('uuid', trim($channelUuid))->first()
            : null;

        if (! $channel instanceof Channel) {
            throw new \RuntimeException('Channel outbox channel could not be resolved.');
        }

        return $channel;
    }
}
