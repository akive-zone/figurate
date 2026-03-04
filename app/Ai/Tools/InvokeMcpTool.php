<?php

namespace App\Ai\Tools;

use App\Ai\Support\Mcp\McpInvocationPolicy;
use App\Ai\Support\Mcp\McpServerClient;
use App\Ai\Tools\Diagnostics\EncodesToolResponse;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\ThreadEvent;
use App\Models\Server\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Stringable;
use Throwable;

class InvokeMcpTool implements Tool
{
    use EncodesToolResponse;

    public function __construct(
        protected Thread $thread,
        protected User $actor,
        protected ?ThreadActor $threadActor = null,
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
            $this->recordEvent(
                server: $server,
                tool: $tool,
                successful: false,
                state: ThreadEvent::StateFailed,
                errorMessage: (string) ($authorization['reason'] ?? 'Tool invocation denied.'),
            );

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
        } catch (Throwable $exception) {
            $this->recordEvent(
                server: $server,
                tool: $tool,
                successful: false,
                state: ThreadEvent::StateFailed,
                errorMessage: $exception->getMessage(),
            );

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

        $this->recordEvent(
            server: $server,
            tool: $tool,
            successful: (bool) ($result['ok'] ?? false),
            state: (bool) ($result['ok'] ?? false) ? ThreadEvent::StateCompleted : ThreadEvent::StateFailed,
            errorMessage: is_string($result['error_message'] ?? null) ? (string) $result['error_message'] : null,
        );

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

    protected function recordEvent(
        string $server,
        string $tool,
        bool $successful,
        string $state,
        ?string $errorMessage = null,
    ): void {
        $this->thread->events()->create([
            'thread_actor_id' => $this->threadActor?->id,
            'message_id' => null,
            'event_key' => 'mcp_invoke_tool',
            'layer' => ThreadEvent::LayerExecution,
            'kind' => ThreadEvent::KindMcp,
            'operation' => "{$server}.{$tool}",
            'state' => $state,
            'event_type' => $successful ? 'mcp.invocation.success' : 'mcp.invocation.failure',
            'severity' => $successful ? 'low' : 'medium',
            'payload' => [
                'server' => $server,
                'tool' => $tool,
                'actor_id' => $this->actor->id,
                'actor_uuid' => $this->actor->uuid,
                'error_message' => $errorMessage !== null ? mb_substr(trim($errorMessage), 0, 500) : null,
            ],
        ]);
    }
}
