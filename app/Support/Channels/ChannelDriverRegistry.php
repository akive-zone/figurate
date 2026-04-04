<?php

namespace App\Support\Channels;

use App\Contracts\Channels\ChannelDriver;
use App\Models\Server\Channel;
use InvalidArgumentException;

class ChannelDriverRegistry
{
    /**
     * @param  array<string, class-string<ChannelDriver>>  $systems
     */
    public function __construct(
        protected array $systems = [],
    ) {
        if ($this->systems === []) {
            $configuredSystems = config('channels.systems', config('channels.drivers', []));
            $this->systems = is_array($configuredSystems) ? $configuredSystems : [];
        }
    }

    public function resolveByChannel(Channel $channel): ChannelDriver
    {
        return $this->resolveBySystem((string) $channel->driver);
    }

    public function resolveByDriver(string $driver): ChannelDriver
    {
        return $this->resolveBySystem($driver);
    }

    public function resolveBySystem(string $system): ChannelDriver
    {
        $resolvedSystem = trim($system);
        $driverClass = $this->systems[$resolvedSystem] ?? null;

        if (! is_string($driverClass) || trim($driverClass) === '') {
            throw new InvalidArgumentException("Unsupported channel system [{$resolvedSystem}].");
        }

        $instance = app($driverClass);

        if (! $instance instanceof ChannelDriver) {
            throw new InvalidArgumentException("Channel system [{$resolvedSystem}] must implement ".ChannelDriver::class.'.');
        }

        return $instance;
    }
}
