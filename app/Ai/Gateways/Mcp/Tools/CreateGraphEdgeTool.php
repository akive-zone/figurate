<?php

namespace App\Ai\Gateways\Mcp\Tools;

use App\Ai\Gateways\Mcp\Support\FigurateMcpPayloads;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a typed graph edge between spaces, threads, and posts.')]
class CreateGraphEdgeTool extends Tool
{
    public function handle(Request $request, FigurateMcpPayloads $payloads): Response
    {
        $validated = $request->validate([
            'source_type' => ['required', 'string', 'in:space,thread,post'],
            'source_id' => ['required', 'string'],
            'target_type' => ['required', 'string', 'in:space,thread,post'],
            'target_id' => ['required', 'string'],
            'edge_type' => ['required', 'string', 'in:related_to,references,depends_on,blocks,derived_from,child_of'],
            'purpose' => ['nullable', 'string', 'max:1000'],
        ]);

        $actor = $payloads->actor($request);
        $source = $payloads->resolveGraphNode(
            $actor,
            (string) $validated['source_type'],
            (string) $validated['source_id'],
            true,
        );
        $target = $payloads->resolveGraphNode(
            $actor,
            (string) $validated['target_type'],
            (string) $validated['target_id'],
        );

        $relation = match (true) {
            method_exists($source, 'attachRelation') => $source->attachRelation(
                $target,
                (string) $validated['edge_type'],
                is_string($validated['purpose'] ?? null) ? (string) $validated['purpose'] : null,
            ),
            default => abort(422, 'The selected source does not support graph edges.'),
        };

        return Response::json([
            'edge' => $payloads->mapGraphEdge([
                'relation' => $relation,
                'source' => $source,
                'target' => $target,
                'direction' => 'outgoing',
                'depth' => 1,
            ]),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'source_type' => $schema->string()->description('The source node type.')->enum('space', 'thread', 'post')->required(),
            'source_id' => $schema->string()->description('The source node public ID.')->required(),
            'target_type' => $schema->string()->description('The target node type.')->enum('space', 'thread', 'post')->required(),
            'target_id' => $schema->string()->description('The target node public ID.')->required(),
            'edge_type' => $schema->string()->description('The typed edge label.')->enum(
                'related_to',
                'references',
                'depends_on',
                'blocks',
                'derived_from',
                'child_of',
            )->required(),
            'purpose' => $schema->string()->description('Optional note describing why the edge exists.'),
        ];
    }
}
