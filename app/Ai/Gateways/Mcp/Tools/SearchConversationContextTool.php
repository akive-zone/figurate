<?php

namespace App\Ai\Gateways\Mcp\Tools;

use App\Ai\Gateways\Mcp\Support\FigurateMcpPayloads;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Search messages, posts, and linked store documents in Figurate context.')]
class SearchConversationContextTool extends Tool
{
    public function handle(Request $request, FigurateMcpPayloads $payloads): Response
    {
        $validated = $request->validate([
            'query' => ['required', 'string'],
            'channel_id' => ['nullable', 'string'],
            'thread_id' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $actor = $payloads->actor($request);
        $channel = is_string($validated['channel_id'] ?? null)
            ? $payloads->resolveChannel($actor, (string) $validated['channel_id'])
            : null;
        $thread = is_string($validated['thread_id'] ?? null)
            ? $payloads->resolveThread($actor, (string) $validated['thread_id'])
            : null;

        return Response::json([
            'results' => $payloads->searchContext(
                actor: $actor,
                query: (string) $validated['query'],
                channel: $channel,
                thread: $thread,
                limit: (int) ($validated['limit'] ?? 10),
            ),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('The search text.')->required(),
            'channel_id' => $schema->string()->description('Optional channel UUID used to scope the search.'),
            'thread_id' => $schema->string()->description('Optional thread UUID used to scope the search.'),
            'limit' => $schema->integer()->description('Maximum number of matches to return.')->default(10),
        ];
    }
}
