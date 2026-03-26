<?php

namespace App\Support\Graph;

use App\Models\Server\Space;
use App\Models\Server\SpaceRelation;
use App\Models\Server\Thread;
use App\Models\Server\ThreadRelation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class GraphEdgeExplorer
{
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
            default => null,
        };

        if ($query === null) {
            return collect();
        }

        if ($edgeType !== null) {
            $query->where('type', $edgeType);
        }

        return $query
            ->with('relationable')
            ->get()
            ->filter(function (SpaceRelation|ThreadRelation $relation) use ($targetType): bool {
                return $this->matchesTargetType($relation->relationable, $targetType);
            })
            ->map(function (SpaceRelation|ThreadRelation $relation) use ($node, $depth): array {
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
            ->filter(function (SpaceRelation|ThreadRelation $relation) use ($targetType): bool {
                $source = $relation instanceof SpaceRelation
                    ? $relation->space
                    : $relation->thread;

                return $source instanceof Model && $this->matchesTargetType($source, $targetType);
            })
            ->map(function (SpaceRelation|ThreadRelation $relation) use ($node, $depth): array {
                /** @var Model $source */
                $source = $relation instanceof SpaceRelation
                    ? $relation->space
                    : $relation->thread;

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
     * @return Collection<int, SpaceRelation|ThreadRelation>
     */
    protected function incomingRelationsForNode(Model $node, ?string $edgeType): Collection
    {
        $spaceRelations = match (true) {
            $node instanceof Space => $node->inboundSpaceRelations($edgeType),
            $node instanceof Thread => $node->inboundSpaceRelations($edgeType),
            default => collect(),
        };

        $threadRelations = match (true) {
            $node instanceof Space => $node->inboundThreadRelations($edgeType),
            $node instanceof Thread => $node->inboundThreadRelations($edgeType),
            default => collect(),
        };

        return $spaceRelations
            ->merge($threadRelations)
            ->loadMissing(['space', 'thread']);
    }

    protected function matchesTargetType(?Model $model, ?string $targetType): bool
    {
        if (! $model instanceof Model) {
            return false;
        }

        if ($targetType === null || $targetType === '') {
            return $model instanceof Space || $model instanceof Thread;
        }

        return match ($targetType) {
            'space' => $model instanceof Space,
            'thread' => $model instanceof Thread,
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeEdgePayload(
        SpaceRelation|ThreadRelation $relation,
        Model $source,
        Model $target,
        Model $adjacent,
        string $direction,
        int $depth,
    ): array {
        return [
            'edge_key' => sprintf(
                '%s:%s:%s:%s:%s',
                $this->nodeKey($source),
                $relation->type,
                $this->nodeKey($target),
                $direction,
                $depth,
            ),
            'relation' => $relation,
            'source' => $source,
            'target' => $target,
            'adjacent' => $adjacent,
            'direction' => $direction,
            'depth' => $depth,
        ];
    }

    protected function nodeKey(Model $model): string
    {
        return sprintf('%s:%s', $model->getMorphClass(), $model->getKey());
    }
}
