<?php

namespace App\Ai\Support\Mcp;

use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use Illuminate\Database\Eloquent\Model;

class ContextServerRegistry
{
    public function __construct(
        protected McpRemoteEndpointClient $remoteEndpointClient = new McpRemoteEndpointClient,
    ) {}

    /**
     * @param  array<string, string>  $headers
     */
    public function registerRemoteServer(
        Model $channelable,
        string $server,
        string $endpointUrl,
        array $headers = [],
        ?string $label = null,
        int $priority = 0
    ): Channel {
        if (! method_exists($channelable, 'contextServers')) {
            throw new \RuntimeException('Channelable model does not support context servers.');
        }

        $tools = $this->remoteEndpointClient->listTools(
            endpointUrl: $endpointUrl,
            headers: $headers,
        );

        $contextServer = $channelable->contextServers()
            ->where('server', $server)
            ->orderByDesc('id')
            ->first();

        if (! $contextServer instanceof Channel) {
            $contextServer = Channel::query()->create([
                'name' => $label ?: $server,
                'driver' => Channel::DriverMcp,
                'server' => $server,
                'label' => $label,
                'enabled' => true,
                'priority' => $priority,
                'transport' => 'remote',
                'endpoint_url' => $endpointUrl,
                'handler' => null,
                'allowed_tools' => $tools,
                'auth_type' => 'header',
                'credentials' => [
                    'headers' => $headers,
                ],
            ]);
        } else {
            $contextServer->forceFill([
                'name' => $label ?: $server,
                'label' => $label,
                'enabled' => true,
                'priority' => $priority,
                'transport' => 'remote',
                'endpoint_url' => $endpointUrl,
                'handler' => null,
                'allowed_tools' => $tools,
                'auth_type' => 'header',
                'credentials' => [
                    'headers' => $headers,
                ],
            ])->save();
        }

        $channelable->channelRelations()->updateOrCreate(
            [
                'channel_id' => $contextServer->id,
                'kind' => ChannelRelation::KindLink,
            ],
            [
                'status' => Channel::StatusActive,
                'direction' => Channel::DirectionBidirectional,
                'data' => [],
                'meta' => [],
            ],
        );

        return $contextServer;
    }
}
