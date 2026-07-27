<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Graph\QueryGraphEdgesRequest;
use App\Http\Requests\Server\Graph\StoreGraphEdgeRequest;
use App\Http\Requests\Server\Graph\UpdateGraphEdgeRequest;
use App\Models\Server\PostRelation;
use App\Models\Server\SpaceRelation;
use App\Models\Server\ThreadRelation;
use App\Models\Server\User;
use App\Support\Graph\GraphEdgeExplorer;
use App\Support\Graph\GraphNodeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GraphEdgeController extends Controller
{
    public function __construct(
        protected GraphEdgeExplorer $graphEdgeExplorer,
        protected GraphNodeService $graphNodes,
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
            ->map(fn (Model $relatedNode): array => $this->graphNodes->map($relatedNode, $actor))
            ->all();

        return response()->json([
            'data' => $edges
                ->map(fn (array $edge): array => $this->mapEdge($edge, $actor))
                ->all(),
            'root' => $this->graphNodes->map($node, $actor),
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
        $source = $this->graphNodes->resolve(
            $actor,
            (string) $validated['source_type'],
            (string) $validated['source_id'],
            true,
        );
        $target = $this->graphNodes->resolve(
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

        return response()->json([
            'data' => $this->mapEdge([
                'relation' => $relation,
                'source' => $source,
                'target' => $target,
                'direction' => GraphEdgeExplorer::DirectionOutgoing,
                'depth' => 1,
            ], $actor),
        ], 201);
    }

    public function update(UpdateGraphEdgeRequest $request, string $edge): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $relation = $this->resolveEdge($edge);
        $source = $this->sourceForRelation($relation);
        $target = $relation->relationable;
        abort_unless($source instanceof Model && $target instanceof Model, 404);
        Gate::forUser($actor)->authorize('update', $source);
        $validated = $request->validated();

        if ($relation instanceof PostRelation) {
            abort_if(array_key_exists('purpose', $validated), 422, 'Post edges do not support a purpose.');
            $relation->fill([
                'role' => $validated['edge_type'] ?? $relation->role,
            ])->save();
        } else {
            $relation->fill([
                'type' => $validated['edge_type'] ?? $relation->type,
                'purpose' => array_key_exists('purpose', $validated)
                    ? $validated['purpose']
                    : $relation->purpose,
            ])->save();
        }

        return response()->json([
            'data' => $this->mapEdge([
                'relation' => $relation->refresh(),
                'source' => $source,
                'target' => $target,
                'direction' => GraphEdgeExplorer::DirectionOutgoing,
                'depth' => 1,
            ], $actor),
        ]);
    }

    public function destroy(Request $request, string $edge): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $relation = $this->resolveEdge($edge);
        $source = $this->sourceForRelation($relation);
        abort_unless($source instanceof Model, 404);
        Gate::forUser($actor)->authorize('update', $source);
        $relation->delete();

        return response()->json(status: 204);
    }

    /**
     * @param  array<string, mixed>  $edge
     * @return array<string, mixed>
     */
    protected function mapEdge(array $edge, User $actor): array
    {
        /** @var SpaceRelation|ThreadRelation|PostRelation $relation */
        $relation = $edge['relation'];
        /** @var Model $source */
        $source = $edge['source'];
        /** @var Model $target */
        $target = $edge['target'];

        return [
            'id' => $relation->ulid,
            'direction' => (string) $edge['direction'],
            'depth' => (int) $edge['depth'],
            'type' => $relation instanceof PostRelation ? $relation->role : $relation->type,
            'purpose' => $relation instanceof PostRelation ? null : $relation->purpose,
            'source' => $this->graphNodes->map($source, $actor),
            'target' => $this->graphNodes->map($target, $actor),
            'created_at' => optional($relation->created_at)?->toIso8601String(),
        ];
    }

    protected function resolveEdge(string $edge): SpaceRelation|ThreadRelation|PostRelation
    {
        foreach ([SpaceRelation::class, ThreadRelation::class, PostRelation::class] as $relationClass) {
            $relation = $relationClass::query()
                ->with('relationable')
                ->where('ulid', $edge)
                ->first();

            if (
                ($relation instanceof SpaceRelation || $relation instanceof ThreadRelation || $relation instanceof PostRelation)
                && ! in_array(
                    $relation instanceof PostRelation ? $relation->role : $relation->type,
                    GraphEdgeExplorer::ReservedEdgeTypes,
                    true,
                )
            ) {
                return $relation;
            }
        }

        abort(404, 'Edge not found.');
    }

    protected function sourceForRelation(SpaceRelation|ThreadRelation|PostRelation $relation): ?Model
    {
        return match (true) {
            $relation instanceof SpaceRelation => $relation->space,
            $relation instanceof ThreadRelation => $relation->thread,
            $relation instanceof PostRelation => $relation->post,
        };
    }
}
