<?php

namespace App\Features\Actions\Conversation\Protocols;

use App\Features\Actions\Conversation\ChannelOutboundMessageSender;
use App\Features\Actions\Conversation\Contracts\InboundMessageReceiver;
use App\Features\Actions\Conversation\Contracts\OutboundMessageSender;
use App\Features\Actions\Conversation\Contracts\ProtocolDriver;
use App\Features\Actions\Conversation\ProtocolWebhook;

class ChannelProtocol implements ProtocolDriver
{
    public const Key = 'channel';

    public function __construct(protected ChannelOutboundMessageSender $channelOutboundMessageSender) {}

    public function key(): string
    {
        return self::Key;
    }

    /**
     * @return array<string, InboundMessageReceiver>
     */
    public function inboundReceivers(): array
    {
        return [];
    }

    public function outboundSender(): ?OutboundMessageSender
    {
        return $this->channelOutboundMessageSender;
    }

    /**
     * @return list<ProtocolWebhook>
     */
    public function webhooks(): array
    {
        return [];
    }

    public function registerRoutes(): void {}
}
