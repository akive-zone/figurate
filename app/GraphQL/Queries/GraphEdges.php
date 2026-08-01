<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\Server\User;
use App\Support\Auth\ApiAbilityGate;
use App\Support\Graph\GraphEdgeExplorer;
use App\Support\Graph\GraphNodeService;
use App\Support\Graph\GraphPayloadMapper;
use GraphQL\Error\UserError;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final readonly class GraphEdges
{
    public function __construct(
        private GraphEdgeExplorer $graphEdgeExplorer,
        private GraphNodeService $graphNodes,
        private GraphPayloadMapper $graphPayloads,
        private ApiAbilityGate $apiAbilities,
    ) {}

    /**
     * @param  array{
     *     nodeType: string,
     *     nodeId: string,
     *     direction?: string,
     *     edgeType?: string|null,
     *     targetType?: string|null,
     *     depth?: int,
     *     limit?: int
     * }  $args
     * @return array<string, mixed>
     */
    public function __invoke(null $root, array $args, GraphQLContext $context): array
    {
        $actor = $context->user();

        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }

        if (! $this->apiAbilities->allows($actor, 'edges:read')) {
            throw new AuthorizationException(
                'The API credential does not have the required edges:read ability.',
            );
        }

        $edgeType = $args['edgeType'] ?? null;
        if (is_string($edgeType) && in_array($edgeType, GraphEdgeExplorer::ReservedEdgeTypes, true)) {
            throw new UserError('The edge type is not supported.');
        }

        $node = $this->graphNodes->resolve($actor, $args['nodeType'], $args['nodeId']);
        $direction = $args['direction'] ?? GraphEdgeExplorer::DirectionOutgoing;
        $depth = $args['depth'] ?? 1;
        $edges = $this->graphEdgeExplorer->explore(
            root: $node,
            direction: $direction,
            edgeType: $edgeType,
            targetType: $args['targetType'] ?? null,
            depth: $depth,
            limit: $args['limit'] ?? 25,
        );
        $nodes = $edges
            ->flatMap(fn (array $edge): array => [$edge['source'], $edge['target']])
            ->prepend($node)
            ->unique(fn (Model $relatedNode): string => sprintf(
                '%s:%s',
                $relatedNode->getMorphClass(),
                $relatedNode->getKey(),
            ))
            ->values();

        return [
            'root' => $this->graphPayloads->node($node, $actor),
            'nodes' => $nodes
                ->map(fn (Model $relatedNode): array => $this->graphPayloads->node($relatedNode, $actor))
                ->all(),
            'edges' => $edges
                ->map(fn (array $edge): array => $this->graphPayloads->edge($edge, $actor))
                ->all(),
            'meta' => [
                'direction' => $direction,
                'depth' => $depth,
                'edge_type' => $edgeType,
                'target_type' => $args['targetType'] ?? null,
                'edge_count' => $edges->count(),
                'node_count' => $nodes->count(),
            ],
        ];
    }
}
