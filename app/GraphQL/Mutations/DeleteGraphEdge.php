<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\GraphQL\Support\GraphQLAuthorizer;
use App\Support\Graph\GraphMutationService;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final readonly class DeleteGraphEdge
{
    public function __construct(
        private GraphQLAuthorizer $authorizer,
        private GraphMutationService $graphMutations,
    ) {}

    /**
     * @param  array{id: string}  $args
     * @return array{id: string}
     */
    public function __invoke(null $root, array $args, GraphQLContext $context): array
    {
        $actor = $this->authorizer->actor($context, 'edges:write');

        return $this->authorizer->mutate(fn (): array => [
            'id' => $this->graphMutations->deleteEdge($actor, $args['id']),
        ]);
    }
}
