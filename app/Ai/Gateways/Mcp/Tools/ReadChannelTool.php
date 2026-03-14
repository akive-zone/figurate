<?php

namespace App\Ai\Gateways\Mcp\Tools;

use App\Ai\Gateways\Mcp\Support\FigurateMcpPayloads;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Read a channel and include recent threads and posts.')]
class ReadChannelTool extends Tool
{
    public function handle(Request $request, FigurateMcpPayloads $payloads): Response
    {
        $validated = $request->validate([
            'channel_id' => ['required', 'string'],
            'thread_limit' => ['nullable', 'integer', 'min:1', 'max:25'],
            'post_limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $actor = $payloads->actor($request);
        $channel = $payloads->resolveChannel($actor, (string) $validated['channel_id']);
        $threadLimit = (int) ($validated['thread_limit'] ?? 10);
        $postLimit = (int) ($validated['post_limit'] ?? 10);

        return Response::json([
            'channel' => $payloads->mapChannel($channel),
            'threads' => $channel->conversationThreads()
                ->sortByDesc('id')
                ->take($threadLimit)
                ->values()
                ->map(fn ($thread): array => $payloads->mapThread($thread))
                ->all(),
            'posts' => $channel->conversationPosts()
                ->sortByDesc('id')
                ->take($postLimit)
                ->values()
                ->map(fn ($post): array => $payloads->mapPost($post))
                ->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'channel_id' => $schema->string()->description('The channel UUID.')->required(),
            'thread_limit' => $schema->integer()->description('Maximum number of threads to include.')->default(10),
            'post_limit' => $schema->integer()->description('Maximum number of posts to include.')->default(10),
        ];
    }
}
