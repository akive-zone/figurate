<?php

namespace App\Features\Actions\Conversation;

use App\Features\Actions\Conversation\Contracts\InboundMessageReceiver;
use App\Features\Actions\Conversation\Contracts\OutboundMessageSender;
use App\Features\Actions\Conversation\Contracts\ProtocolDriver;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Route;

class ProtocolRegistry
{
    public const DriverTag = 'server.protocol-driver';

    public function __construct(protected Container $container) {}

    /**
     * @return array<string, ProtocolDriver>
     */
    public function all(): array
    {
        $drivers = [];

        foreach ($this->container->tagged(self::DriverTag) as $driver) {
            if (! $driver instanceof ProtocolDriver) {
                continue;
            }

            $drivers[$this->normalize($driver->key())] = $driver;
        }

        return $drivers;
    }

    public function driver(string $protocol): ?ProtocolDriver
    {
        return $this->all()[$this->normalize($protocol)] ?? null;
    }

    public function inboundReceiver(string $protocol, string $transport): ?InboundMessageReceiver
    {
        $driver = $this->driver($protocol);

        if (! $driver) {
            return null;
        }

        $receivers = $driver->inboundReceivers();
        $normalizedTransport = $this->normalize($transport);

        return $receivers[$normalizedTransport] ?? $receivers['*'] ?? null;
    }

    public function outboundSender(string $protocol): ?OutboundMessageSender
    {
        return $this->driver($protocol)?->outboundSender();
    }

    public function protocolForWebhookConfig(string $configName): ?string
    {
        $normalizedConfigName = trim($configName);

        foreach ($this->all() as $driver) {
            foreach ($driver->webhooks() as $webhook) {
                if ($webhook->configName === $normalizedConfigName) {
                    return $driver->key();
                }
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function webhookConfigs(): array
    {
        $configs = [];

        foreach ($this->all() as $driver) {
            foreach ($driver->webhooks() as $webhook) {
                $configs[$webhook->configName] = $webhook->toConfig();
            }
        }

        return array_values($configs);
    }

    public function registerRoutes(): void
    {
        foreach ($this->all() as $driver) {
            foreach ($driver->webhooks() as $webhook) {
                Route::middleware('api')
                    ->prefix('api')
                    ->group(function () use ($webhook): void {
                        Route::webhooks($webhook->path, $webhook->configName, $webhook->method);
                    });
            }

            $driver->registerRoutes();
        }
    }

    protected function normalize(string $value): string
    {
        return strtolower(trim($value));
    }
}
