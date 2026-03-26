<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Graph\QueryGraphEdgesRequest;
use App\Http\Requests\Server\Graph\StoreGraphEdgeRequest;
use App\Models\Server\Space;
use App\Models\Server\SpaceRelation;
use App\Models\Server\Thread;
use App\Models\Server\ThreadRelation;
use App\Models\Server\User;
use App\Support\Graph\GraphEdgeExplorer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class GraphEdgeController extends Controller
{
    public function __construct(protected GraphEdgeExplorer $graphEdgeExplorer) {}

    public function index(QueryGraphEdgesRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $node = $this->resolveNode(
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
            ->map(fn (Model $relatedNode): array => $this->mapNode($relatedNode))
            ->all();

        return response()->json([
            'data' => $edges
                ->map(fn (array $edge): array => $this->mapEdge($edge))
                ->all(),
            'root' => $this->mapNode($node),
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
        $source = $this->resolveNode(
            $actor,
            (string) $validated['source_type'],
            (string) $validated['source_id'],
            true,
        );
        $target = $this->resolveNode(
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
            ]),
        ], 201);
    }

    protected function resolveNode(User $actor, string $type, string $uuid, bool $forUpdate = false): Model
    {
        return match ($type) {
            'space' => $this->resolveSpace($actor, $uuid, $forUpdate),
            'thread' => $this->resolveThread($actor, $uuid, $forUpdate),
            default => abort(422, 'Unsupported graph node type.'),
        };
    }

    protected function resolveSpace(User $actor, string $uuid, bool $forUpdate = false): Space
    {
        $space = Space::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        Gate::forUser($actor)->authorize($forUpdate ? 'update' : 'view', $space);

        return $space;
    }

    protected function resolveThread(User $actor, string $uuid, bool $forUpdate = false): Thread
    {
        $thread = Thread::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        Gate::forUser($actor)->authorize($forUpdate ? 'update' : 'view', $thread);

        return $thread;
    }

    /**
     * @param  array<string, mixed>  $edge
     * @return array<string, mixed>
     */
    protected function mapEdge(array $edge): array
    {
        /** @var SpaceRelation|ThreadRelation $relation */
        $relation = $edge['relation'];
        /** @var Model $source */
        $source = $edge['source'];
        /** @var Model $target */
        $target = $edge['target'];

        return [
            'direction' => (string) $edge['direction'],
            'depth' => (int) $edge['depth'],
            'type' => $relation->type,
            'purpose' => $relation->purpose,
            'source' => $this->mapNode($source),
            'target' => $this->mapNode($target),
            'created_at' => optional($relation->created_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapNode(Model $node): array
    {
        return match (true) {
            $node instanceof Space => [
                'type' => 'space',
                'id' => $node->uuid,
                'status' => $node->status,
                'thread_count' => $node->conversationThreadIds()->count(),
                'post_count' => $node->conversationPosts()->count(),
            ],
            $node instanceof Thread => [
                'type' => 'thread',
                'id' => $node->uuid,
                'title' => $node->title ?: 'Thread',
                'purpose' => $node->purpose,
                'phase' => $node->phase,
                'status' => $node->status,
            ],
            default => abort(422, 'Unsupported graph node model.'),
        };
    }
}
