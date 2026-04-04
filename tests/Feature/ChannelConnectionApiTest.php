<?php

namespace Tests\Feature;

use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use App\Models\Server\User;
use App\TokenAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChannelConnectionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_stdio_connection_for_a_stdio_channel(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create([
            'driver' => Channel::DriverStdio,
            'server' => 'worker-stdio',
            'label' => 'Worker Stdio',
        ]);

        Sanctum::actingAs($user, [TokenAbility::Compose->value]);

        $response = $this->postJson(route('api.channels.connections.store', ['channel' => $channel->id]), [
            'owner_type' => 'user',
            'owner_id' => 'me',
            'kind' => ChannelRelation::KindLink,
            'direction' => Channel::DirectionBidirectional,
            'transport' => 'stdio',
            'mode' => 'local',
            'config' => [
                'command' => 'npx',
                'args' => ['-y', '@modelcontextprotocol/server-filesystem', '/workspace'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.channel.driver', Channel::DriverStdio)
            ->assertJsonPath('data.owner.type', 'user')
            ->assertJsonPath('data.transport', 'stdio')
            ->assertJsonPath('data.mode', 'local')
            ->assertJsonPath('data.config.command', 'npx');

        $connection = $user->channelRelations()->where('channel_id', $channel->id)->first();

        $this->assertInstanceOf(ChannelRelation::class, $connection);
        $this->assertSame('stdio', data_get($connection->config, 'transport'));
        $this->assertSame('local', data_get($connection->config, 'mode'));
        $this->assertSame('npx', data_get($connection->config, 'command'));
        $this->assertSame(ChannelRelation::KindLink, $connection->kind);
    }

    public function test_it_creates_a_websocket_connection_for_an_a2a_protocol(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create([
            'driver' => 'websocket',
            'server' => 'agent-socket',
        ]);

        Sanctum::actingAs($user, [TokenAbility::Compose->value]);

        $response = $this->postJson(route('api.channels.connections.store', ['channel' => $channel->id]), [
            'owner_type' => 'user',
            'owner_id' => 'me',
            'direction' => Channel::DirectionBidirectional,
            'protocol' => Channel::ProtocolA2a,
            'transport' => 'websocket',
            'config' => [
                'endpoint_url' => 'wss://agents.example/socket',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.channel.driver', 'websocket')
            ->assertJsonPath('data.channel.system', 'websocket')
            ->assertJsonPath('data.protocol', Channel::ProtocolA2a)
            ->assertJsonPath('data.transport', 'websocket');

        $connection = $user->channelRelations()->where('channel_id', $channel->id)->first();

        $this->assertInstanceOf(ChannelRelation::class, $connection);
        $this->assertSame(Channel::ProtocolA2a, data_get($connection->config, 'protocol'));
        $this->assertSame('websocket', data_get($connection->config, 'transport'));
    }

    public function test_it_rejects_acp_connections_for_mcp_channels(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create([
            'driver' => Channel::DriverMcp,
            'server' => 'filesystem',
        ]);

        Sanctum::actingAs($user, [TokenAbility::Compose->value]);

        $response = $this->postJson(route('api.channels.connections.store', ['channel' => $channel->id]), [
            'owner_type' => 'user',
            'owner_id' => 'me',
            'protocol' => Channel::ProtocolAcp,
            'transport' => 'http',
            'config' => [
                'endpoint_url' => 'https://agents.example/mcp',
            ],
        ]);

        $response->assertStatus(422)
            ->assertInvalid([
                'config.protocol' => 'The selected protocol is not supported for the [mcp] channel system.',
            ]);
    }
}
