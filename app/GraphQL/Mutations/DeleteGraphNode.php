<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\GraphQL\Support\GraphQLAuthorizer;
use App\Support\Graph\GraphMutationService;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final readonly class DeleteGraphNode
{
    public function __construct(
        private GraphQLAuthorizer $authorizer,
        private GraphMutationService $graphMutations,
    ) {}

    /**
     * @param  array{type: string, id: string}  $args
     * @return array{type: 'space'|'thread'|'post', id: string}
     */
    public function __invoke(null $root, array $args, GraphQLContext $context): array
    {
        $actor = $this->authorizer->actor($context, 'nodes:write');

        return $this->authorizer->mutate(
            fn (): array => $this->graphMutations->deleteNode($actor, $args['type'], $args['id']),
        );
    }
}
