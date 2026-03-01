<?php

namespace App\Ai\Tools;

use App\Ai\Support\Mcp\McpInvocationPolicy;
use App\Ai\Support\Mcp\McpServerClient;
use App\Ai\Tools\Diagnostics\EncodesToolResponse;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Stringable;

class InvokeMcpTool implements Tool
{
    use EncodesToolResponse;

    public function __construct(
        protected Thread $thread,
        protected User $actor,
        protected McpServerClient $serverClient = new McpServerClient,
        protected McpInvocationPolicy $policy = new McpInvocationPolicy,
    ) {}

    public function description(): Stringable|string
    {
        return 'Invoke an allowlisted MCP tool for an available context server and return a normalized response envelope.';
    }

    public function handle(ToolRequest $request): Stringable|string
    {
        $server = trim((string) ($request['server'] ?? ''));
        $tool = trim((string) ($request['tool'] ?? ''));
        $arguments = $request['arguments'] ?? [];
        $idempotencyKey = $request['idempotency_key'] ?? null;
        $timeoutMs = isset($request['timeout_ms']) ? (int) $request['timeout_ms'] : null;

        if ($server === '' || $tool === '') {
            return $this->error('Both server and tool are required.');
        }

        if (! is_array($arguments)) {
            return $this->error('arguments must be a JSON object.');
        }

        $authorization = $this->policy->authorize($server, $tool, $this->thread, $this->actor);
        if (! ($authorization['allowed'] ?? false)) {
            return $this->ok([
                'allowed' => false,
                'server' => $server,
                'tool' => $tool,
                'error' => (string) ($authorization['reason'] ?? 'Tool invocation denied.'),
                'context' => $authorization['context'] ?? null,
            ]);
        }

        $context = is_array($authorization['context'] ?? null) ? $authorization['context'] : [];

        try {
            $result = $this->serverClient->callTool(
                server: $server,
                tool: $tool,
                arguments: $arguments,
                context: $context,
                idempotencyKey: is_string($idempotencyKey) ? $idempotencyKey : null,
                timeoutMs: $timeoutMs,
            );
        } catch (\Throwable $exception) {
            return $this->ok([
                'allowed' => true,
                'server' => $server,
                'tool' => $tool,
                'context' => $context,
                'ok' => false,
                'error_code' => 'mcp_exception',
                'error_message' => $exception->getMessage(),
                'data' => null,
            ]);
        }

        return $this->ok([
            'allowed' => true,
            'server' => $server,
            'tool' => $tool,
            'context' => $context,
            ...$result,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'server' => $schema->string()->required(),
            'tool' => $schema->string()->required(),
            'arguments' => $schema->object(),
            'idempotency_key' => $schema->string(),
            'timeout_ms' => $schema->integer(),
        ];
    }
}
