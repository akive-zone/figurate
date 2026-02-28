<?php

namespace App\Ai\Support\Mcp;

use App\Models\Server\ContextServer;
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
        Model $contextable,
        string $server,
        string $endpointUrl,
        array $headers = [],
        ?string $label = null,
        int $priority = 0
    ): ContextServer {
        if (! method_exists($contextable, 'contextServers')) {
            throw new \RuntimeException('Contextable model does not support context servers.');
        }

        $tools = $this->remoteEndpointClient->listTools(
            endpointUrl: $endpointUrl,
            headers: $headers,
        );

        return $contextable->contextServers()->updateOrCreate(
            ['server' => $server],
            [
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
            ],
        );
    }
}
