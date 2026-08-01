<?php

namespace App\Support\Graph;

use App\Models\Server\PostRelation;
use App\Models\Server\SpaceRelation;
use App\Models\Server\ThreadRelation;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GraphMutationService
{
    public function __construct(
        protected GraphNodeService $graphNodes,
        protected NodeFormer $nodeFormer,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function createNode(User $actor, array $input): Model
    {
        return $this->nodeFormer->form($actor, $input)['node'];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateNode(User $actor, string $type, string $nodeId, array $attributes): Model
    {
        return $this->nodeFormer->form($actor, [
            'type' => $type,
            'id' => $nodeId,
            'attributes' => $attributes,
        ])['node'];
    }

    /**
     * @return array{type: 'space'|'thread'|'post', id: string}
     */
    public function deleteNode(User $actor, string $type, string $nodeId): array
    {
        $node = $this->graphNodes->resolve($actor, $type, $nodeId);
        Gate::forUser($actor)->authorize('delete', $node);

        abort_if(
            $this->graphNodes->children($actor, $node)->isNotEmpty(),
            409,
            'A node with child nodes cannot be deleted.',
        );

        $reference = $this->graphNodes->reference($node);

        DB::transaction(function () use ($node): void {
            if (method_exists($node, 'relations')) {
                $node->relations()->delete();
            }

            foreach (['inboundSpaceRelations', 'inboundThreadRelations', 'inboundPostRelations'] as $method) {
                if (method_exists($node, $method)) {
                    $node->{$method}()->each->delete();
                }
            }

            $node->delete();
        });

        return $reference;
    }

    /**
     * @return array<string, mixed>
     */
    public function createEdge(
        User $actor,
        string $sourceType,
        string $sourceId,
        string $targetType,
        string $targetId,
        string $edgeType,
        ?string $purpose = null,
    ): array {
        $source = $this->graphNodes->resolve($actor, $sourceType, $sourceId, true);
        $target = $this->graphNodes->resolve($actor, $targetType, $targetId);

        $relation = match (true) {
            method_exists($source, 'attachRelation') => $source->attachRelation($target, $edgeType, $purpose),
            default => abort(422, 'The selected source does not support graph edges.'),
        };

        return $this->edgeResult($relation, $source, $target);
    }

    /**
     * @param  array{edge_type?: string, purpose?: string|null}  $attributes
     * @return array<string, mixed>
     */
    public function updateEdge(User $actor, string $edgeId, array $attributes): array
    {
        $relation = $this->resolveEdge($edgeId);
        $source = $this->sourceForRelation($relation);
        $target = $relation->relationable;
        abort_unless($source instanceof Model && $target instanceof Model, 404);
        Gate::forUser($actor)->authorize('update', $source);

        if ($relation instanceof PostRelation) {
            abort_if(array_key_exists('purpose', $attributes), 422, 'Post edges do not support a purpose.');
            $relation->fill([
                'role' => $attributes['edge_type'] ?? $relation->role,
            ])->save();
        } else {
            $relation->fill([
                'type' => $attributes['edge_type'] ?? $relation->type,
                'purpose' => array_key_exists('purpose', $attributes)
                    ? $attributes['purpose']
                    : $relation->purpose,
            ])->save();
        }

        return $this->edgeResult($relation->refresh(), $source, $target);
    }

    public function deleteEdge(User $actor, string $edgeId): string
    {
        $relation = $this->resolveEdge($edgeId);
        $source = $this->sourceForRelation($relation);
        abort_unless($source instanceof Model, 404);
        Gate::forUser($actor)->authorize('update', $source);
        $relation->delete();

        return $edgeId;
    }

    public function resolveEdge(string $edgeId): SpaceRelation|ThreadRelation|PostRelation
    {
        foreach ([SpaceRelation::class, ThreadRelation::class, PostRelation::class] as $relationClass) {
            $relation = $relationClass::query()
                ->with('relationable')
                ->where('ulid', $edgeId)
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

    public function sourceForRelation(SpaceRelation|ThreadRelation|PostRelation $relation): ?Model
    {
        return match (true) {
            $relation instanceof SpaceRelation => $relation->space,
            $relation instanceof ThreadRelation => $relation->thread,
            $relation instanceof PostRelation => $relation->post,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function edgeResult(
        SpaceRelation|ThreadRelation|PostRelation $relation,
        Model $source,
        Model $target,
    ): array {
        return [
            'relation' => $relation,
            'source' => $source,
            'target' => $target,
            'direction' => GraphEdgeExplorer::DirectionOutgoing,
            'depth' => 1,
        ];
    }
}
