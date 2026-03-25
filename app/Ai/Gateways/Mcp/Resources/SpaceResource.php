<?php

namespace App\Ai\Gateways\Mcp\Resources;

use App\Ai\Gateways\Mcp\Support\FigurateMcpPayloads;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('Read a Figurate space by URI template.')]
class SpaceResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('file://figurate/spaces/{spaceId}');
    }

    public function handle(Request $request, FigurateMcpPayloads $payloads): Response
    {
        $actor = $payloads->actor($request);
        $space = $payloads->resolveSpace($actor, (string) $request->get('spaceId'));

        return Response::text(json_encode([
            'space' => $payloads->mapSpace($space),
            'threads' => $space->conversationThreads()
                ->sortByDesc('id')
                ->take(10)
                ->values()
                ->map(fn ($thread): array => $payloads->mapThread($thread))
                ->all(),
            'posts' => $space->conversationPosts()
                ->sortByDesc('id')
                ->take(10)
                ->values()
                ->map(fn ($post): array => $payloads->mapPost($post))
                ->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
