<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Graph\QueryGraphEdgesRequest;
use App\Http\Requests\Server\Graph\StoreGraphEdgeRequest;
use App\Http\Requests\Server\Graph\UpdateGraphEdgeRequest;
use App\Models\Server\User;
use App\Support\Graph\GraphEdgeExplorer;
use App\Support\Graph\GraphMutationService;
use App\Support\Graph\GraphNodeService;
use App\Support\Graph\GraphPayloadMapper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GraphEdgeController extends Controller
{
    public function __construct(
        protected GraphEdgeExplorer $graphEdgeExplorer,
        protected GraphNodeService $graphNodes,
        protected GraphPayloadMapper $graphPayloads,
        protected GraphMutationService $graphMutations,
    ) {}

    public function index(QueryGraphEdgesRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $node = $this->graphNodes->resolve(
            $actor,
            (string) $validated['node_type'],
            (string) $validated['node_id'],
        );
        $direction = (string) ($validated['direction'] ?? GraphEdgeExplorer::DirectionOutgoing);
        $depth = (int) ($validated['depth'] ?? 1);
        $edges = $this->graphEdgeExplorer->explore(
            root: $node,
            direction: $direction,
            edgeType: is_string($validated['edge_type'] ?? null) ? (string) $validated['edge_type'] : null,
            targetType: is_string($validated['target_type'] ?? null) ? (string) $validated['target_type'] : null,
            depth: $depth,
            limit: (int) ($validated['limit'] ?? 25),
        );

        $nodes = $edges
            ->flatMap(fn (array $edge): array => [$edge['source'], $edge['target']])
            ->prepend($node)
            ->unique(fn (Model $relatedNode): string => sprintf('%s:%s', $relatedNode->getMorphClass(), $relatedNode->getKey()))
            ->values()
            ->map(fn (Model $relatedNode): array => $this->graphPayloads->node($relatedNode, $actor))
            ->all();

        return response()->json([
            'data' => $edges
                ->map(fn (array $edge): array => $this->graphPayloads->edge($edge, $actor))
                ->all(),
            'root' => $this->graphPayloads->node($node, $actor),
            'nodes' => $nodes,
            'meta' => [
                'direction' => $direction,
                'depth' => $depth,
                'edge_type' => $validated['edge_type'] ?? null,
                'target_type' => $validated['target_type'] ?? null,
                'edge_count' => $edges->count(),
                'node_count' => count($nodes),
            ],
        ]);
    }

    public function store(StoreGraphEdgeRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $edge = $this->graphMutations->createEdge(
            actor: $actor,
            sourceType: (string) $validated['source_type'],
            sourceId: (string) $validated['source_id'],
            targetType: (string) $validated['target_type'],
            targetId: (string) $validated['target_id'],
            edgeType: (string) $validated['edge_type'],
            purpose: is_string($validated['purpose'] ?? null) ? (string) $validated['purpose'] : null,
        );

        return response()->json([
            'data' => $this->graphPayloads->edge($edge, $actor),
        ], 201);
    }

    public function update(UpdateGraphEdgeRequest $request, string $edge): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $updatedEdge = $this->graphMutations->updateEdge($actor, $edge, $validated);

        return response()->json([
            'data' => $this->graphPayloads->edge($updatedEdge, $actor),
        ]);
    }

    public function destroy(Request $request, string $edge): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->graphMutations->deleteEdge($actor, $edge);

        return response()->json(status: 204);
    }
}
