<?php

namespace Figurate\LegacyProtocols\Conversation\Protocols;

use App\Features\Actions\Chat\Contracts\InboundMessageReceiver;
use App\Features\Actions\Chat\Contracts\OutboundMessageSender;
use App\Features\Actions\Chat\Contracts\ProtocolDriver;
use App\Features\Actions\Chat\HttpInboundMessageReceiver;
use App\Features\Actions\Chat\ProtocolWebhook;
use App\Features\Actions\Chat\WebhookInboundMessageReceiver;
use Figurate\LegacyProtocols\Conversation\NostrOutboundMessageSender;
use Figurate\LegacyProtocols\Conversation\NostrRelayInboundMessageReceiver;

class NostrProtocol implements ProtocolDriver
{
    public function __construct(
        protected HttpInboundMessageReceiver $httpInboundMessageReceiver,
        protected WebhookInboundMessageReceiver $webhookInboundMessageReceiver,
        protected NostrRelayInboundMessageReceiver $nostrRelayInboundMessageReceiver,
        protected NostrOutboundMessageSender $nostrOutboundMessageSender,
    ) {}

    public function key(): string
    {
        return 'nostr';
    }

    /**
     * @return array<string, InboundMessageReceiver>
     */
    public function inboundReceivers(): array
    {
        return [
            'http' => $this->httpInboundMessageReceiver,
            'webhook' => $this->webhookInboundMessageReceiver,
            'relay' => $this->nostrRelayInboundMessageReceiver,
            'websocket' => $this->nostrRelayInboundMessageReceiver,
            'nostr_relay' => $this->nostrRelayInboundMessageReceiver,
        ];
    }

    public function outboundSender(): ?OutboundMessageSender
    {
        return $this->nostrOutboundMessageSender;
    }

    /**
     * @return list<ProtocolWebhook>
     */
    public function webhooks(): array
    {
        return [
            new ProtocolWebhook(
                configName: 'chat_inbound.nostr',
                path: 'webhooks/chats/nostr',
                signingSecret: (string) config('webhook-client.chat_protocols.nostr.signing_secret', ''),
                signatureHeaderName: (string) config('webhook-client.chat_protocols.nostr.signature_header_name', 'Signature'),
            ),
        ];
    }

    public function registerRoutes(): void {}
}
