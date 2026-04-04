<?php

namespace Tests\Unit;

use App\Ai\Support\Mcp\McpServerResolver;
use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use App\Models\Server\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpServerResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prefers_connection_runtime_fields_for_context_servers(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create([
            'driver' => Channel::DriverMcp,
            'server' => 'filesystem',
            'transport' => 'http',
            'endpoint_url' => 'https://agents.example/mcp',
            'allowed_tools' => ['search'],
        ]);

        $user->channelRelations()->create([
            'channel_id' => $channel->id,
            'kind' => ChannelRelation::KindLink,
            'status' => Channel::StatusActive,
            'direction' => Channel::DirectionBidirectional,
            'config' => [
                'transport' => 'http',
                'mode' => 'remote',
                'endpoint_url' => 'https://override.example/mcp',
                'default_timeout_ms' => 12000,
            ],
            'data' => [],
            'meta' => [],
        ]);

        $resolved = app(McpServerResolver::class)->resolve('filesystem', null, $user);

        $this->assertSame('http', $resolved['transport']);
        $this->assertSame('remote', $resolved['mode']);
        $this->assertSame('https://override.example/mcp', $resolved['endpoint_url']);
        $this->assertSame(12000, data_get($resolved, 'config.default_timeout_ms'));
        $this->assertSame(['search'], $resolved['tools']);
        $this->assertSame('User', $resolved['context_source']);
    }
}
