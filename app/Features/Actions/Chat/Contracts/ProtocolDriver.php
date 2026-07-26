<?php

namespace App\Features\Actions\Chat\Contracts;

use App\Features\Actions\Chat\ProtocolWebhook;

interface ProtocolDriver
{
    public function key(): string;

    /**
     * @return array<string, InboundMessageReceiver>
     */
    public function inboundReceivers(): array;

    public function outboundSender(): ?OutboundMessageSender;

    /**
     * @return list<ProtocolWebhook>
     */
    public function webhooks(): array;

    public function registerRoutes(): void;
}
