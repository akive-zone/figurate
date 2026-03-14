<?php

namespace App\Ai\Gateways\Mcp\Tools;

use App\Ai\Gateways\Mcp\Support\FigurateMcpPayloads;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List posts, optionally scoped to a channel or thread.')]
class ListPostsTool extends Tool
{
    public function handle(Request $request, FigurateMcpPayloads $payloads): Response
    {
        $validated = $request->validate([
            'channel_id' => ['nullable', 'string'],
            'thread_id' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $actor = $payloads->actor($request);
        $limit = (int) ($validated['limit'] ?? 10);

        if (is_string($validated['thread_id'] ?? null)) {
            $thread = $payloads->resolveThread($actor, (string) $validated['thread_id']);
            $posts = $thread->posts()->latest('id')->take($limit)->get();
        } elseif (is_string($validated['channel_id'] ?? null)) {
            $channel = $payloads->resolveChannel($actor, (string) $validated['channel_id']);
            $posts = $channel->conversationPosts()->sortByDesc('id')->take($limit)->values();
        } else {
            $posts = collect();
        }

        return Response::json([
            'posts' => $posts->map(fn ($post): array => $payloads->mapPost($post))->all(),
            'count' => $posts->count(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'channel_id' => $schema->string()->description('Optional channel UUID used to scope posts.'),
            'thread_id' => $schema->string()->description('Optional thread UUID used to scope posts.'),
            'limit' => $schema->integer()->description('Maximum number of posts to return.')->default(10),
        ];
    }
}
