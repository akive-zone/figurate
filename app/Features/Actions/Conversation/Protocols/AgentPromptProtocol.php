<?php

namespace App\Features\Actions\Conversation\Protocols;

use App\Features\Actions\Conversation\AgentPromptOutboundMessageSender;
use App\Features\Actions\Conversation\Contracts\InboundMessageReceiver;
use App\Features\Actions\Conversation\Contracts\OutboundMessageSender;
use App\Features\Actions\Conversation\Contracts\ProtocolDriver;
use App\Features\Actions\Conversation\ProtocolWebhook;

class AgentPromptProtocol implements ProtocolDriver
{
    public const Key = 'agent_prompt';

    public function __construct(protected AgentPromptOutboundMessageSender $agentPromptOutboundMessageSender) {}

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
        return $this->agentPromptOutboundMessageSender;
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
