<?php

namespace App\Support\Graph;

use App\Models\Server\Post;
use App\Models\Server\PostRelation;
use App\Models\Server\Space;
use App\Models\Server\SpaceRelation;
use App\Models\Server\Thread;
use App\Models\Server\ThreadRelation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class GraphEdgeExplorer
{
    public const ReservedEdgeTypes = [
        SpaceRelation::TypeChildOf,
        Post::RelationRoleSender,
    ];

    public const DirectionOutgoing = 'outgoing';

    public const DirectionIncoming = 'incoming';

    public const DirectionBoth = 'both';

    /**
     * @return list<string>
     */
    public function allowedDirections(): array
    {
        return [
            self::DirectionOutgoing,
            self::DirectionIncoming,
            self::DirectionBoth,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function explore(
        Model $root,
        string $direction = self::DirectionOutgoing,
        ?string $edgeType = null,
        ?string $targetType = null,
        int $depth = 1,
        int $limit = 50,
    ): Collection {
        $direction = in_array($direction, $this->allowedDirections(), true)
            ? $direction
            : self::DirectionOutgoing;
        $depth = max(1, min(5, $depth));
        $limit = max(1, min(100, $limit));
        $edges = collect();
        $frontier = collect([$root]);
        $visitedNodes = collect([$this->nodeKey($root)]);
        $visitedEdges = collect();

        for ($level = 1; $level <= $depth; $level++) {
            if ($frontier->isEmpty() || $edges->count() >= $limit) {
                break;
            }

            $nextFrontier = collect();

            foreach ($frontier as $node) {
                $discovered = $this->edgesForNode($node, $direction, $edgeType, $targetType, $level);

                foreach ($discovered as $edge) {
                    $edgeKey = (string) $edge['edge_key'];

                    if ($visitedEdges->contains($edgeKey)) {
                        continue;
                    }

                    $visitedEdges->push($edgeKey);
                    $edges->push($edge);

                    if ($edges->count() >= $limit) {
                        break 2;
                    }

                    /** @var Model $adjacentNode */
                    $adjacentNode = $edge['adjacent'];
                    $adjacentKey = $this->nodeKey($adjacentNode);

                    if ($visitedNodes->contains($adjacentKey)) {
                        continue;
                    }

                    $visitedNodes->push($adjacentKey);
                    $nextFrontier->push($adjacentNode);
                }
            }

            $frontier = $nextFrontier;
        }

        return $edges->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function edgesForNode(
        Model $node,
        string $direction,
        ?string $edgeType,
        ?string $targetType,
        int $depth,
    ): Collection {
        return match ($direction) {
            self::DirectionIncoming => $this->incomingEdgesForNode($node, $edgeType, $targetType, $depth),
            self::DirectionBoth => $this->outgoingEdgesForNode($node, $edgeType, $targetType, $depth)
                ->merge($this->incomingEdgesForNode($node, $edgeType, $targetType, $depth)),
            default => $this->outgoingEdgesForNode($node, $edgeType, $targetType, $depth),
        };
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function outgoingEdgesForNode(
        Model $node,
        ?string $edgeType,
        ?string $targetType,
        int $depth,
    ): Collection {
        $query = match (true) {
            $node instanceof Space => $node->relations(),
            $node instanceof Thread => $node->relations(),
            $node instanceof Post => $node->relations(),
            default => null,
        };

        if ($query === null) {
            return collect();
        }

        $column = $node instanceof Post ? 'role' : 'type';
        if ($edgeType !== null) {
            $query->where($column, $edgeType);
        } else {
            $query->whereNotIn($column, self::ReservedEdgeTypes);
        }

        return $query
            ->with('relationable')
            ->get()
            ->filter(function (SpaceRelation|ThreadRelation|PostRelation $relation) use ($targetType): bool {
                return $this->matchesTargetType($relation->relationable, $targetType);
            })
            ->map(function (SpaceRelation|ThreadRelation|PostRelation $relation) use ($node, $depth): array {
                /** @var Model $target */
                $target = $relation->relationable;

                return $this->makeEdgePayload(
                    relation: $relation,
                    source: $node,
                    target: $target,
                    adjacent: $target,
                    direction: self::DirectionOutgoing,
                    depth: $depth,
                );
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function incomingEdgesForNode(
        Model $node,
        ?string $edgeType,
        ?string $targetType,
        int $depth,
    ): Collection {
        return $this->incomingRelationsForNode($node, $edgeType)
            ->filter(function (SpaceRelation|ThreadRelation|PostRelation $relation) use ($targetType): bool {
                $source = $this->sourceNodeForRelation($relation);

                return $source instanceof Model && $this->matchesTargetType($source, $targetType);
            })
            ->map(function (SpaceRelation|ThreadRelation|PostRelation $relation) use ($node, $depth): array {
                /** @var Model $source */
                $source = $this->sourceNodeForRelation($relation);

                return $this->makeEdgePayload(
                    relation: $relation,
                    source: $source,
                    target: $node,
                    adjacent: $source,
                    direction: self::DirectionIncoming,
                    depth: $depth,
                );
            })
            ->values();
    }

    /**
     * @return Collection<int, SpaceRelation|ThreadRelation|PostRelation>
     */
    protected function incomingRelationsForNode(Model $node, ?string $edgeType): Collection
    {
        $spaceRelations = match (true) {
            $node instanceof Space => $node->inboundSpaceRelations($edgeType),
            $node instanceof Thread => $node->inboundSpaceRelations($edgeType),
            $node instanceof Post => $node->inboundSpaceRelations($edgeType),
            default => collect(),
        };

        $threadRelations = match (true) {
            $node instanceof Space => $node->inboundThreadRelations($edgeType),
            $node instanceof Thread => $node->inboundThreadRelations($edgeType),
            $node instanceof Post => $node->inboundThreadRelations($edgeType),
            default => collect(),
        };

        $postRelations = match (true) {
            $node instanceof Space => $node->inboundPostRelations($edgeType),
            $node instanceof Thread => $node->inboundPostRelations($edgeType),
            $node instanceof Post => $node->inboundPostRelations($edgeType),
            default => collect(),
        };

        return $spaceRelations
            ->merge($threadRelations)
            ->merge($postRelations)
            ->filter(fn (SpaceRelation|ThreadRelation|PostRelation $relation): bool => in_array(
                $this->relationType($relation),
                self::ReservedEdgeTypes,
                true,
            ) === false)
            ->each(function (SpaceRelation|ThreadRelation|PostRelation $relation): void {
                match (true) {
                    $relation instanceof SpaceRelation => $relation->loadMissing('space'),
                    $relation instanceof ThreadRelation => $relation->loadMissing('thread'),
                    $relation instanceof PostRelation => $relation->loadMissing('post'),
                };
            });
    }

    protected function matchesTargetType(?Model $model, ?string $targetType): bool
    {
        if (! $model instanceof Model) {
            return false;
        }

        if ($targetType === null || $targetType === '') {
            return $model instanceof Space || $model instanceof Thread || $model instanceof Post;
        }

        return match ($targetType) {
            'space' => $model instanceof Space,
            'thread' => $model instanceof Thread,
            'post' => $model instanceof Post,
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeEdgePayload(
        SpaceRelation|ThreadRelation|PostRelation $relation,
        Model $source,
        Model $target,
        Model $adjacent,
        string $direction,
        int $depth,
    ): array {
        return [
            'edge_key' => sprintf('%s:%s', $relation->getMorphClass(), $relation->ulid),
            'relation' => $relation,
            'source' => $source,
            'target' => $target,
            'adjacent' => $adjacent,
            'direction' => $direction,
            'depth' => $depth,
        ];
    }

    protected function relationType(SpaceRelation|ThreadRelation|PostRelation $relation): string
    {
        return $relation instanceof PostRelation ? (string) $relation->role : (string) $relation->type;
    }

    protected function sourceNodeForRelation(SpaceRelation|ThreadRelation|PostRelation $relation): ?Model
    {
        return match (true) {
            $relation instanceof SpaceRelation => $relation->space,
            $relation instanceof ThreadRelation => $relation->thread,
            $relation instanceof PostRelation => $relation->post,
            default => null,
        };
    }

    protected function nodeKey(Model $model): string
    {
        return sprintf('%s:%s', $model->getMorphClass(), $model->getKey());
    }
}
