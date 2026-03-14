<?php

namespace App\Ai\Gateways\Mcp\Resources;

use App\Ai\Gateways\Mcp\Support\FigurateMcpPayloads;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('Read a Figurate channel by URI template.')]
class ChannelResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('file://figurate/channels/{channelId}');
    }

    public function handle(Request $request, FigurateMcpPayloads $payloads): Response
    {
        $actor = $payloads->actor($request);
        $channel = $payloads->resolveChannel($actor, (string) $request->get('channelId'));

        return Response::text(json_encode([
            'channel' => $payloads->mapChannel($channel),
            'threads' => $channel->conversationThreads()
                ->sortByDesc('id')
                ->take(10)
                ->values()
                ->map(fn ($thread): array => $payloads->mapThread($thread))
                ->all(),
            'posts' => $channel->conversationPosts()
                ->sortByDesc('id')
                ->take(10)
                ->values()
                ->map(fn ($post): array => $payloads->mapPost($post))
                ->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
