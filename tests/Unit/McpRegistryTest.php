<?php

namespace Tests\Unit;

use App\Ai\Support\Mcp\McpRegistry;
use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use App\Models\Server\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prefers_connection_runtime_fields_for_context_servers(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create([
            'driver' => Channel::ProtocolMcp,
            'server' => 'filesystem',
            'transport' => 'websocket',
            'endpoint_url' => 'wss://agents.example/mcp',
            'allowed_tools' => ['search'],
        ]);

        $user->channelRelations()->create([
            'channel_id' => $channel->id,
            'kind' => ChannelRelation::KindLink,
            'status' => Channel::StatusActive,
            'direction' => Channel::DirectionBidirectional,
            'config' => [
                'transport' => 'websocket',
                'mode' => 'remote',
                'endpoint_url' => 'wss://override.example/mcp',
                'default_timeout_ms' => 12000,
            ],
            'data' => [],
            'meta' => [],
        ]);

        $resolved = app(McpRegistry::class)->resolve('filesystem', null, $user);

        $this->assertSame('websocket', $resolved['transport']);
        $this->assertSame('remote', $resolved['mode']);
        $this->assertSame('wss://override.example/mcp', $resolved['endpoint_url']);
        $this->assertSame(12000, data_get($resolved, 'config.default_timeout_ms'));
        $this->assertSame(['search'], $resolved['tools']);
        $this->assertSame('User', $resolved['context_source']);
    }

    public function test_it_does_not_expose_config_only_servers_without_registered_channels(): void
    {
        config()->set('services.mcp.servers.filesystem', [
            'transport' => 'http',
            'endpoint_url' => 'https://config.example/mcp',
            'tools' => ['search'],
        ]);

        $resolver = app(McpRegistry::class);

        $this->assertFalse($resolver->enabled());
        $this->assertSame([], $resolver->available());

        $resolved = $resolver->resolve('filesystem');

        $this->assertFalse($resolved['enabled']);
        $this->assertSame([], $resolved['tools']);
        $this->assertNull($resolved['endpoint_url']);
    }
}
