<?php

namespace Tests\Unit;

use App\Models\Server\Channel;
use App\Support\Channels\ChannelDriverRegistry;
use App\Support\Channels\Drivers\A2aChannelDriver;
use App\Support\Channels\Drivers\AcpChannelDriver;
use App\Support\Channels\Drivers\GenericChannelDriver;
use App\Support\Channels\Drivers\McpChannelDriver;
use App\Support\Channels\Drivers\StdioChannelDriver;
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

    public function test_it_throws_for_unsupported_systems(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported channel system [unsupported].');

        app(ChannelDriverRegistry::class)->resolveBySystem('unsupported');
    }

    public function test_it_resolves_the_mcp_channel_driver(): void
    {
        $channel = new Channel([
            'driver' => Channel::DriverMcp,
        ]);

        $resolved = app(ChannelDriverRegistry::class)->resolveByChannel($channel);

        $this->assertInstanceOf(McpChannelDriver::class, $resolved);
    }

    public function test_it_resolves_the_stdio_channel_driver(): void
    {
        $channel = new Channel([
            'driver' => Channel::DriverStdio,
        ]);

        $resolved = app(ChannelDriverRegistry::class)->resolveByChannel($channel);

        $this->assertInstanceOf(StdioChannelDriver::class, $resolved);
    }

    public function test_it_resolves_the_a2a_channel_driver(): void
    {
        $channel = new Channel([
            'driver' => Channel::DriverA2a,
        ]);

        $resolved = app(ChannelDriverRegistry::class)->resolveByChannel($channel);

        $this->assertInstanceOf(A2aChannelDriver::class, $resolved);
        $this->assertSame([Channel::ProtocolA2a], $resolved->supportedProtocols());
    }

    public function test_it_resolves_the_acp_channel_driver(): void
    {
        $channel = new Channel([
            'driver' => Channel::DriverAcp,
        ]);

        $resolved = app(ChannelDriverRegistry::class)->resolveByChannel($channel);

        $this->assertInstanceOf(AcpChannelDriver::class, $resolved);
        $this->assertSame([Channel::ProtocolAcp], $resolved->supportedProtocols());
    }

    public function test_it_exposes_protocol_support_for_websocket_channels(): void
    {
        $channel = new Channel([
            'driver' => 'websocket',
        ]);

        $resolved = app(ChannelDriverRegistry::class)->resolveByChannel($channel);

        $this->assertSame([
            Channel::ProtocolMcp,
            Channel::ProtocolA2a,
            Channel::ProtocolAcp,
        ], $resolved->supportedProtocols());
    }
}
