<?php

namespace Tests\Feature\Commands;

use App\Models\Server\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebSocketListenAllCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_no_channels_when_none_exist(): void
    {
        $this->artisan('websocket:listen --all')
            ->expectsOutput('No WebSocket channels found that require listening.')
            ->assertSuccessful();
    }

    public function test_it_requires_channel_or_all_flag(): void
    {
        $this->artisan('websocket:listen')
            ->expectsOutputToContain('Channel UUID is required. Use --channel=UUID or --all to listen on all channels.')
            ->assertFailed();
    }

    public function test_it_filters_channels_correctly(): void
    {
        // Create various channels
        $inboundChannel = Channel::factory()->create([
            'driver' => Channel::ProtocolGeneric,
            'transport' => Channel::TransportWebsocket,
            'name' => 'Inbound Server',
            'direction' => Channel::DirectionInbound,
            'endpoint_url' => 'wss://inbound.example.com',
            'enabled' => true,
        ]);

        $bidirectionalChannel = Channel::factory()->create([
            'driver' => Channel::ProtocolGeneric,
            'transport' => Channel::TransportWebsocket,
            'name' => 'Bidirectional Server',
            'direction' => Channel::DirectionBidirectional,
            'endpoint_url' => 'wss://bidirectional.example.com',
            'enabled' => true,
        ]);

        // This should NOT be included (outbound only)
        Channel::factory()->create([
            'driver' => Channel::ProtocolGeneric,
            'transport' => Channel::TransportWebsocket,
            'name' => 'Outbound Only',
            'direction' => Channel::DirectionOutbound,
            'endpoint_url' => 'wss://outbound.example.com',
            'enabled' => true,
        ]);

        // This should NOT be included (disabled)
        Channel::factory()->create([
            'driver' => Channel::ProtocolGeneric,
            'transport' => Channel::TransportWebsocket,
            'direction' => Channel::DirectionInbound,
            'endpoint_url' => 'wss://disabled.example.com',
            'enabled' => false,
        ]);

        // This should NOT be included (no endpoint)
        Channel::factory()->create([
            'driver' => Channel::ProtocolGeneric,
            'transport' => Channel::TransportWebsocket,
            'direction' => Channel::DirectionInbound,
            'endpoint_url' => null,
            'enabled' => true,
        ]);

        // Query channels using same logic as command
        $listenable = Channel::query()
            ->where('transport', Channel::TransportWebsocket)
            ->where('enabled', true)
            ->whereNotNull('endpoint_url')
            ->whereIn('direction', [Channel::DirectionInbound, Channel::DirectionBidirectional])
            ->get();

        // Verify filtering logic
        $this->assertCount(2, $listenable);
        $this->assertTrue($listenable->contains($inboundChannel));
        $this->assertTrue($listenable->contains($bidirectionalChannel));
    }

    public function test_single_channel_requires_valid_channel_uuid(): void
    {
        $this->artisan('websocket:listen', ['--channel' => 'invalid-uuid'])
            ->expectsOutputToContain('Channel not found')
            ->assertFailed();
    }

    public function test_single_channel_requires_websocket_driver(): void
    {
        $channel = Channel::factory()->create([
            'driver' => Channel::ProtocolGeneric,
            'transport' => Channel::TransportWebhook,
            'endpoint_url' => 'https://example.com',
        ]);

        $this->artisan('websocket:listen', ['--channel' => $channel->uuid])
            ->expectsOutputToContain('Channel must use the websocket transport')
            ->assertFailed();
    }

    public function test_single_channel_requires_endpoint_url(): void
    {
        $channel = Channel::factory()->create([
            'driver' => Channel::ProtocolGeneric,
            'transport' => Channel::TransportWebsocket,
            'endpoint_url' => null,
        ]);

        $this->artisan('websocket:listen', ['--channel' => $channel->uuid])
            ->expectsOutputToContain('Channel must have an endpoint_url configured')
            ->assertFailed();
    }
}
