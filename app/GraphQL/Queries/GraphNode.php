<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\GraphQL\Support\GraphQLAuthorizer;
use App\Support\Graph\GraphNodeService;
use App\Support\Graph\GraphPayloadMapper;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final readonly class GraphNode
{
    public function __construct(
        private GraphNodeService $graphNodes,
        private GraphPayloadMapper $graphPayloads,
        private GraphQLAuthorizer $authorizer,
    ) {}

    /**
     * @param  array{type: string, id: string}  $args
     * @return array<string, mixed>
     */
    public function __invoke(null $root, array $args, GraphQLContext $context): array
    {
        $actor = $this->authorizer->actor($context, 'nodes:read');

        $node = $this->graphNodes->resolve($actor, $args['type'], $args['id']);

        return $this->graphPayloads->node($node, $actor);
    }
}
