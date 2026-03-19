<?php

namespace App\Features\Actions\Chat\Protocols;

use App\Features\Actions\Chat\ActivityPubOutboundMessageSender;
use App\Features\Actions\Chat\ChatProtocolWebhook;
use App\Features\Actions\Chat\Contracts\ChatProtocolDriver;
use App\Features\Actions\Chat\Contracts\InboundMessageReceiver;
use App\Features\Actions\Chat\Contracts\OutboundMessageSender;
use App\Features\Actions\Chat\HttpInboundMessageReceiver;
use App\Features\Actions\Chat\WebhookInboundMessageReceiver;

class ActivityPubChatProtocol implements ChatProtocolDriver
{
    public function __construct(
        protected HttpInboundMessageReceiver $httpInboundMessageReceiver,
        protected WebhookInboundMessageReceiver $webhookInboundMessageReceiver,
        protected ActivityPubOutboundMessageSender $activityPubOutboundMessageSender,
    ) {}

    public function key(): string
    {
        return 'activitypub';
    }

    /**
     * @return array<string, InboundMessageReceiver>
     */
    public function inboundReceivers(): array
    {
        return [
            'http' => $this->httpInboundMessageReceiver,
            'webhook' => $this->webhookInboundMessageReceiver,
        ];
    }

    public function outboundSender(): ?OutboundMessageSender
    {
        return $this->activityPubOutboundMessageSender;
    }

    /**
     * @return list<ChatProtocolWebhook>
     */
    public function webhooks(): array
    {
        return [
            new ChatProtocolWebhook(
                configName: 'chat_inbound.activitypub',
                path: 'webhooks/chats/activitypub',
                signingSecret: (string) config('webhook-client.chat_protocols.activitypub.signing_secret', ''),
                signatureHeaderName: (string) config('webhook-client.chat_protocols.activitypub.signature_header_name', 'Signature'),
            ),
        ];
    }

    public function registerRoutes(): void {}
}
