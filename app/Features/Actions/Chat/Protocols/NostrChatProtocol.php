<?php

namespace App\Features\Actions\Chat\Protocols;

use App\Features\Actions\Chat\ChatProtocolWebhook;
use App\Features\Actions\Chat\Contracts\ChatProtocolDriver;
use App\Features\Actions\Chat\Contracts\InboundMessageReceiver;
use App\Features\Actions\Chat\Contracts\OutboundMessageSender;
use App\Features\Actions\Chat\HttpInboundMessageReceiver;
use App\Features\Actions\Chat\NostrOutboundMessageSender;
use App\Features\Actions\Chat\NostrRelayInboundMessageReceiver;
use App\Features\Actions\Chat\WebhookInboundMessageReceiver;

class NostrChatProtocol implements ChatProtocolDriver
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
     * @return list<ChatProtocolWebhook>
     */
    public function webhooks(): array
    {
        return [
            new ChatProtocolWebhook(
                configName: 'chat_inbound.nostr',
                path: 'webhooks/chats/nostr',
                signingSecret: (string) config('webhook-client.chat_protocols.nostr.signing_secret', ''),
                signatureHeaderName: (string) config('webhook-client.chat_protocols.nostr.signature_header_name', 'Signature'),
            ),
        ];
    }

    public function registerRoutes(): void {}
}
