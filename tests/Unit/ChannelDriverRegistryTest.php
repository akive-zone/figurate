<?php

namespace Tests\Unit;

use App\Models\Server\Channel;
use App\Support\Channels\ChannelDriverRegistry;
use App\Support\Channels\Drivers\GenericChannelDriver;
use InvalidArgumentException;
use Tests\TestCase;

class ChannelDriverRegistryTest extends TestCase
{
    public function test_it_resolves_the_channel_driver_from_channel_metadata(): void
    {
        $channel = new Channel([
            'driver' => Channel::DriverGeneric,
        ]);

        $resolved = app(ChannelDriverRegistry::class)->resolveByChannel($channel);

        $this->assertInstanceOf(GenericChannelDriver::class, $resolved);
    }

    public function test_it_throws_for_unsupported_drivers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported channel driver [unsupported].');

        app(ChannelDriverRegistry::class)->resolveByDriver('unsupported');
    }
}
