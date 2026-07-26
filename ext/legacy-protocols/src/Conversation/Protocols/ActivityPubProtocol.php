<?php

namespace Figurate\LegacyProtocols\Conversation\Protocols;

use App\Features\Actions\Chat\Contracts\InboundMessageReceiver;
use App\Features\Actions\Chat\Contracts\OutboundMessageSender;
use App\Features\Actions\Chat\Contracts\ProtocolDriver;
use App\Features\Actions\Chat\HttpInboundMessageReceiver;
use App\Features\Actions\Chat\ProtocolWebhook;
use App\Features\Actions\Chat\WebhookInboundMessageReceiver;
use Figurate\LegacyProtocols\Conversation\ActivityPubOutboundMessageSender;

class ActivityPubProtocol implements ProtocolDriver
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
     * @return list<ProtocolWebhook>
     */
    public function webhooks(): array
    {
        return [
            new ProtocolWebhook(
                configName: 'chat_inbound.activitypub',
                path: 'webhooks/chats/activitypub',
                signingSecret: (string) config('webhook-client.chat_protocols.activitypub.signing_secret', ''),
                signatureHeaderName: (string) config('webhook-client.chat_protocols.activitypub.signature_header_name', 'Signature'),
            ),
        ];
    }

    public function registerRoutes(): void {}
}
