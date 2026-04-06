<?php

namespace App\Support\Channels;

use App\Contracts\Channels\ChannelDriver;
use App\Models\Server\Channel;
use InvalidArgumentException;

class ChannelDriverRegistry
{
    /**
     * @param  array<string, class-string<ChannelDriver>>  $protocols
     */
    public function __construct(
        protected array $protocols = [],
    ) {
        if ($this->protocols === []) {
            $configuredProtocols = config('channels.protocols', []);
            $this->protocols = is_array($configuredProtocols) ? $configuredProtocols : [];
        }
    }

    public function resolveByChannel(Channel $channel): ChannelDriver
    {
        return $this->resolveByProtocol($channel->protocolKey());
    }

    public function resolveByDriver(string $driver): ChannelDriver
    {
        return $this->resolveByProtocol($driver);
    }

    public function resolveBySystem(string $system): ChannelDriver
    {
        return $this->resolveByProtocol($system);
    }

    public function resolveByProtocol(string $protocol): ChannelDriver
    {
        $resolvedProtocol = $this->normalizeProtocol($protocol);
        $driverClass = $this->protocols[$resolvedProtocol] ?? null;

        if (! is_string($driverClass) || trim($driverClass) === '') {
            throw new InvalidArgumentException("Unsupported channel protocol [{$resolvedProtocol}].");
        }

        $instance = app($driverClass);

        if (! $instance instanceof ChannelDriver) {
            throw new InvalidArgumentException("Channel protocol [{$resolvedProtocol}] must implement ".ChannelDriver::class.'.');
        }

        return $instance;
    }

    protected function normalizeProtocol(string $protocol): string
    {
        $normalized = strtolower(trim($protocol));

        if ($normalized === '') {
            return Channel::ProtocolGeneric;
        }

        if (in_array($normalized, Channel::supportedProtocols(), true)) {
            return $normalized;
        }

        return $normalized;
    }
}
