<?php

namespace App\Ai\Support\Mcp;

use App\Models\Server\Channel;
use App\Support\Channels\ChannelRegistry;
use Illuminate\Database\Eloquent\Model;

class ContextServerRegistry
{
    public function __construct(
        protected ChannelRegistry $channelRegistry,
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
        return $this->channelRegistry->register($channelable, [
            'protocol' => Channel::ProtocolMcp,
            'name' => $server,
            'label' => $label,
            'enabled' => true,
            'priority' => $priority,
            'transport' => Channel::TransportHttp,
            'direction' => Channel::DirectionBidirectional,
            'endpoint_url' => $endpointUrl,
            'allowed_tools' => [],
            'auth_type' => 'header',
            'credentials' => [
                'headers' => $headers,
            ],
            'config' => [
                'mode' => 'remote',
            ],
        ]);
    }
}
