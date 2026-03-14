<?php

namespace App\Actions\Server\Chat\Contracts;

use App\Actions\Server\Chat\ChatProtocolWebhook;

interface ChatProtocolDriver
{
    public function key(): string;

    /**
     * @return array<string, InboundMessageReceiver>
     */
    public function inboundReceivers(): array;

    public function outboundSender(): ?OutboundMessageSender;

    /**
     * @return list<ChatProtocolWebhook>
     */
    public function webhooks(): array;

    public function registerRoutes(): void;
}
