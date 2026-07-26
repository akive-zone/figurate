<?php

namespace Tests\Unit;

use App\Features\Actions\Chat\Contracts\InboundMessageReceiver;
use App\Features\Actions\Chat\Contracts\OutboundMessageSender;
use App\Features\Actions\Chat\Contracts\ProtocolDriver;
use App\Features\Actions\Chat\InboundMessageEnvelope;
use App\Features\Actions\Chat\ProtocolRegistry;
use App\Features\Actions\Chat\ProtocolWebhook;
use App\Jobs\ProcessInboundMessageWebhookJob;
use App\Models\Server\Outbox;
use App\Models\Server\Post;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\SignatureValidator\DefaultSignatureValidator;
use Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile;
use Spatie\WebhookClient\WebhookResponse\DefaultRespondsTo;

class ProtocolRegistryTest extends TestCase
{
    public function test_it_resolves_inbound_receivers_by_protocol_and_transport(): void
    {
        $httpReceiver = new class implements InboundMessageReceiver
        {
            public function receive(InboundMessageEnvelope $envelope): Post
            {
                return new Post;
            }
        };

        $relayReceiver = new class implements InboundMessageReceiver
        {
            public function receive(InboundMessageEnvelope $envelope): Post
            {
                return new Post;
            }
        };

        $registry = $this->makeRegistry([
            new class($httpReceiver, $relayReceiver) implements ProtocolDriver
            {
                public function __construct(
                    protected InboundMessageReceiver $httpReceiver,
                    protected InboundMessageReceiver $relayReceiver,
                ) {}

                public function key(): string
                {
                    return 'nostr';
                }

                public function inboundReceivers(): array
                {
                    return [
                        'http' => $this->httpReceiver,
                        'relay' => $this->relayReceiver,
                    ];
                }

                public function outboundSender(): ?OutboundMessageSender
                {
                    return null;
                }

                public function webhooks(): array
                {
                    return [];
                }

                public function registerRoutes(): void {}
            },
        ]);

        $this->assertSame($httpReceiver, $registry->inboundReceiver('nostr', 'http'));
        $this->assertSame($relayReceiver, $registry->inboundReceiver('nostr', 'relay'));
        $this->assertNull($registry->inboundReceiver('nostr', 'webhook'));
        $this->assertNull($registry->inboundReceiver('activitypub', 'http'));
    }

    public function test_it_resolves_outbound_senders_and_webhook_configs(): void
    {
        $sender = new class implements OutboundMessageSender
        {
            public function send(Outbox $outbox): array
            {
                return ['ok' => true];
            }
        };

        $registry = $this->makeRegistry([
            new class($sender) implements ProtocolDriver
            {
                public function __construct(protected OutboundMessageSender $sender) {}

                public function key(): string
                {
                    return 'activitypub';
                }

                public function inboundReceivers(): array
                {
                    return [];
                }

                public function outboundSender(): ?OutboundMessageSender
                {
                    return $this->sender;
                }

                public function webhooks(): array
                {
                    return [
                        new ProtocolWebhook(
                            configName: 'chat_inbound.activitypub',
                            path: 'webhooks/chats/activitypub',
                            signingSecret: 'secret',
                        ),
                    ];
                }

                public function registerRoutes(): void {}
            },
        ]);

        $this->assertSame($sender, $registry->outboundSender('activitypub'));
        $this->assertSame('activitypub', $registry->protocolForWebhookConfig('chat_inbound.activitypub'));
        $this->assertSame([
            [
                'name' => 'chat_inbound.activitypub',
                'signing_secret' => 'secret',
                'signature_header_name' => 'Signature',
                'signature_validator' => DefaultSignatureValidator::class,
                'webhook_profile' => ProcessEverythingWebhookProfile::class,
                'webhook_response' => DefaultRespondsTo::class,
                'webhook_model' => WebhookCall::class,
                'store_headers' => ['*'],
                'process_webhook_job' => ProcessInboundMessageWebhookJob::class,
            ],
        ], $registry->webhookConfigs());
    }

    public function test_it_uses_wildcard_receiver_when_transport_specific_mapping_is_missing(): void
    {
        $fallbackReceiver = new class implements InboundMessageReceiver
        {
            public function receive(InboundMessageEnvelope $envelope): Post
            {
                return new Post;
            }
        };

        $registry = $this->makeRegistry([
            new class($fallbackReceiver) implements ProtocolDriver
            {
                public function __construct(protected InboundMessageReceiver $fallbackReceiver) {}

                public function key(): string
                {
                    return 'custom';
                }

                public function inboundReceivers(): array
                {
                    return [
                        '*' => $this->fallbackReceiver,
                    ];
                }

                public function outboundSender(): ?OutboundMessageSender
                {
                    return null;
                }

                public function webhooks(): array
                {
                    return [];
                }

                public function registerRoutes(): void {}
            },
        ]);

        $this->assertSame($fallbackReceiver, $registry->inboundReceiver('custom', 'webhook'));
    }

    /**
     * @param  list<ProtocolDriver>  $drivers
     */
    protected function makeRegistry(array $drivers): ProtocolRegistry
    {
        $container = new Container;

        foreach ($drivers as $index => $driver) {
            $abstract = "driver-{$index}";
            $container->instance($abstract, $driver);
            $container->tag($abstract, ProtocolRegistry::DriverTag);
        }

        return new ProtocolRegistry($container);
    }
}
