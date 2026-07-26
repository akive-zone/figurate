<?php

namespace App\Features\Actions\Chat;

use App\Features\Actions\Chat\Contracts\OutboundMessageSender;
use App\Features\Actions\Chat\Protocols\ChannelProtocol;
use App\Models\Server\Channel;
use App\Models\Server\Outbox;
use App\Models\Server\Post;
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
        $post = $this->resolveMessage($outbox);
        $channel = $this->resolveChannel($outbox);

        if (
            $post->postable_type !== $thread->getMorphClass() ||
            $post->postable_id !== $thread->getKey()
        ) {
            throw new \RuntimeException('Channel outbox references mismatched thread resources.');
        }

        $delivery = data_get($outbox->payload, 'delivery');
        $deliveryConfig = is_array($delivery) ? $delivery : [];
        $deliveryConfig['outbound_payload'] = is_array($outbox->payload) ? $outbox->payload : [];
        $result = $this->channelDriverRegistry
            ->resolveByChannel($channel)
            ->send($channel, $thread, $post, $deliveryConfig);

        return [
            'ok' => true,
            'protocol' => ChannelProtocol::Key,
            'provider' => $outbox->provider ?: $channel->driver,
            'target' => $outbox->target,
            'delivery' => data_get($result, 'status', 'queued'),
            'thread_id' => $thread->id,
            'post_id' => $post->id,
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

    protected function resolveMessage(Outbox $outbox): Post
    {
        $post = $outbox->relationLoaded('post') ? $outbox->post : null;
        $post ??= $outbox->post_id ? Post::query()->find($outbox->post_id) : null;

        if (! $post instanceof Post) {
            throw new \RuntimeException('Channel outbox post could not be resolved.');
        }

        return $post;
    }

    protected function resolveChannel(Outbox $outbox): Channel
    {
        $channelUuid = data_get($outbox->payload, 'channel.uuid')
            ?? data_get($outbox->payload, 'delivery.channel.uuid');
        $channel = is_string($channelUuid)
            ? Channel::query()->where('uuid', trim($channelUuid))->first()
            : null;

        if (! $channel instanceof Channel) {
            throw new \RuntimeException('Channel outbox channel could not be resolved.');
        }

        return $channel;
    }
}
