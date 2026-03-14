<?php

namespace App\Ai\Gateways\Mcp\Tools;

use App\Ai\Gateways\Mcp\Support\FigurateMcpPayloads;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List channels the authenticated actor can access.')]
class ListChannelsTool extends Tool
{
    public function handle(Request $request, FigurateMcpPayloads $payloads): Response
    {
        $actor = $payloads->actor($request);
        $limit = max(1, min(25, (int) $request->integer('limit', 10)));
        $channels = $payloads->visibleChannels($actor, $limit)
            ->map(fn ($channel): array => $payloads->mapChannel($channel))
            ->all();

        return Response::json([
            'channels' => $channels,
            'count' => count($channels),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Maximum number of channels to return.')->default(10),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'channels' => $schema->array(),
            'count' => $schema->integer(),
        ];
    }
}
