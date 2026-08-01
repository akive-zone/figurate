<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\GraphQL\Support\GraphMutationInputValidator;
use App\GraphQL\Support\GraphQLAuthorizer;
use App\Support\Graph\GraphMutationService;
use App\Support\Graph\GraphPayloadMapper;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final readonly class CreateGraphEdge
{
    public function __construct(
        private GraphQLAuthorizer $authorizer,
        private GraphMutationInputValidator $inputValidator,
        private GraphMutationService $graphMutations,
        private GraphPayloadMapper $graphPayloads,
    ) {}

    /**
     * @param  array{input: array{
     *     source: array{type: string, id: string},
     *     target: array{type: string, id: string},
     *     edge_type: string,
     *     purpose?: string|null
     * }}  $args
     * @return array<string, mixed>
     */
    public function __invoke(null $root, array $args, GraphQLContext $context): array
    {
        $actor = $this->authorizer->actor($context, 'edges:write');
        $input = $args['input'];
        $this->inputValidator->edgeType($input['edge_type']);

        return $this->authorizer->mutate(function () use ($actor, $input): array {
            $edge = $this->graphMutations->createEdge(
                actor: $actor,
                sourceType: $input['source']['type'],
                sourceId: $input['source']['id'],
                targetType: $input['target']['type'],
                targetId: $input['target']['id'],
                edgeType: $input['edge_type'],
                purpose: $input['purpose'] ?? null,
            );

            return $this->graphPayloads->edge($edge, $actor);
        });
    }
}
