<?php

namespace App\Actions\Server\Chat\Protocols;

use App\Actions\Server\Chat\ActivityPubOutboundMessageSender;
use App\Actions\Server\Chat\ChatProtocolWebhook;
use App\Actions\Server\Chat\Contracts\ChatProtocolDriver;
use App\Actions\Server\Chat\Contracts\InboundMessageReceiver;
use App\Actions\Server\Chat\Contracts\OutboundMessageSender;
use App\Actions\Server\Chat\HttpInboundMessageReceiver;
use App\Actions\Server\Chat\WebhookInboundMessageReceiver;

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
