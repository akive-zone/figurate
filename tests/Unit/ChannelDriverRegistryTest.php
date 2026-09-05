<?php

namespace Tests\Unit;

use App\Models\Server\Channel;
use App\Support\Channels\ChannelDriverRegistry;
use App\Support\Channels\Drivers\GenericChannelDriver;
use InvalidArgumentException;
use Tests\TestCase;

class ChannelDriverRegistryTest extends TestCase
{
    public function test_it_resolves_the_generic_channel_driver(): void
    {
        $channel = new Channel([
            'driver' => Channel::ProtocolGeneric,
        ]);

        $resolved = app(ChannelDriverRegistry::class)->resolveByChannel($channel);

        $this->assertInstanceOf(GenericChannelDriver::class, $resolved);
    }

    public function test_it_throws_for_unsupported_protocols(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported channel protocol [unsupported].');

        app(ChannelDriverRegistry::class)->resolveByProtocol('unsupported');
    }

    public function test_unknown_stored_protocols_fall_back_to_generic(): void
    {
        $channel = new Channel([
            'driver' => 'unknown',
        ]);

        $resolved = app(ChannelDriverRegistry::class)->resolveByChannel($channel);

        $this->assertInstanceOf(GenericChannelDriver::class, $resolved);
    }
}
