<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\Server\User;
use App\Support\Auth\ApiAbilityGate;
use App\Support\Graph\GraphNodeService;
use App\Support\Graph\GraphPayloadMapper;
use Illuminate\Auth\AuthenticationException;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final readonly class GraphNode
{
    public function __construct(
        private GraphNodeService $graphNodes,
        private GraphPayloadMapper $graphPayloads,
        private ApiAbilityGate $apiAbilities,
    ) {}

    /**
     * @param  array{type: string, id: string}  $args
     * @return array<string, mixed>
     */
    public function __invoke(null $root, array $args, GraphQLContext $context): array
    {
        $actor = $context->user();

        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }

        if (! $this->apiAbilities->allows($actor, 'nodes:read')) {
            throw new AuthorizationException(
                'The API credential does not have the required nodes:read ability.',
            );
        }

        $node = $this->graphNodes->resolve($actor, $args['type'], $args['id']);

        return $this->graphPayloads->node($node, $actor);
    }
}
