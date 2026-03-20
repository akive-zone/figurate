<?php

namespace App\Ai\Tools;

use App\Ai\Support\Acp\OutboundAgentRegistry;
use App\Ai\Tools\Diagnostics\EncodesToolResponse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Stringable;

class ListAvailableAcpAgentsTool implements Tool
{
    use EncodesToolResponse;

    public function __construct(
        protected OutboundAgentRegistry $registry = new OutboundAgentRegistry,
    ) {}

    public function description(): Stringable|string
    {
        return 'List allowlisted outbound ACP agents that can be used by this assistant.';
    }

    public function handle(ToolRequest $request): Stringable|string
    {
        if (! $this->registry->enabled()) {
            return $this->ok([
                'enabled' => false,
                'count' => 0,
                'agents' => [],
            ]);
        }

        $includeHeaders = (bool) ($request['include_headers'] ?? false);
        $agents = collect($this->registry->agents())
            ->map(function (array $agent) use ($includeHeaders): array {
                $payload = [
                    'id' => $agent['id'],
                    'label' => $agent['label'] ?? null,
                    'endpoint' => $agent['endpoint'],
                    'transport' => $agent['transport'] ?? 'jsonrpc-http',
                    'auth_type' => $agent['auth_type'] ?? 'none',
                    'has_token' => is_string($agent['token'] ?? null) && trim((string) $agent['token']) !== '',
                    'allowed_methods' => $agent['allowed_methods'] ?? [],
                    'session' => [
                        'reuse' => data_get($agent, 'session.reuse'),
                        'create_method' => data_get($agent, 'session.create_method'),
                        'load_method' => data_get($agent, 'session.load_method'),
                        'prompt_method' => data_get($agent, 'session.prompt_method'),
                    ],
                ];

                if ($includeHeaders) {
                    $payload['headers'] = $agent['headers'] ?? [];
                }

                return $payload;
            })
            ->values()
            ->all();

        return $this->ok([
            'enabled' => true,
            'count' => count($agents),
            'agents' => $agents,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'include_headers' => $schema->boolean(),
        ];
    }
}
