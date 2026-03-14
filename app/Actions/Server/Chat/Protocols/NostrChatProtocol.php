<?php

namespace App\Actions\Server\Chat\Protocols;

use App\Actions\Server\Chat\ChatProtocolWebhook;
use App\Actions\Server\Chat\Contracts\ChatProtocolDriver;
use App\Actions\Server\Chat\Contracts\InboundMessageReceiver;
use App\Actions\Server\Chat\Contracts\OutboundMessageSender;
use App\Actions\Server\Chat\HttpInboundMessageReceiver;
use App\Actions\Server\Chat\NostrOutboundMessageSender;
use App\Actions\Server\Chat\NostrRelayInboundMessageReceiver;
use App\Actions\Server\Chat\WebhookInboundMessageReceiver;

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
