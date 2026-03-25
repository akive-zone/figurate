<?php

namespace App\Support\Channels;

use App\Contracts\Channels\ChannelDriver;
use App\Models\Server\Channel;
use InvalidArgumentException;

class ChannelDriverRegistry
{
    /**
     * @param  array<string, class-string<ChannelDriver>>  $drivers
     */
    public function __construct(
        protected array $drivers = [],
    ) {
        if ($this->drivers === []) {
            $configuredDrivers = config('channels.drivers', []);
            $this->drivers = is_array($configuredDrivers) ? $configuredDrivers : [];
        }
    }

    public function resolveByChannel(Channel $channel): ChannelDriver
    {
        return $this->resolveByDriver((string) $channel->driver);
    }

    public function resolveByDriver(string $driver): ChannelDriver
    {
        $resolvedDriver = trim($driver);
        $driverClass = $this->drivers[$resolvedDriver] ?? null;

        if (! is_string($driverClass) || trim($driverClass) === '') {
            throw new InvalidArgumentException("Unsupported channel driver [{$resolvedDriver}].");
        }

        $instance = app($driverClass);

        if (! $instance instanceof ChannelDriver) {
            throw new InvalidArgumentException("Channel driver [{$resolvedDriver}] must implement ".ChannelDriver::class.'.');
        }

        return $instance;
    }
}
