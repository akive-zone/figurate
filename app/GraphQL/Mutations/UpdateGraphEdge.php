<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\GraphQL\Support\GraphMutationInputValidator;
use App\GraphQL\Support\GraphQLAuthorizer;
use App\Support\Graph\GraphMutationService;
use App\Support\Graph\GraphPayloadMapper;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final readonly class UpdateGraphEdge
{
    public function __construct(
        private GraphQLAuthorizer $authorizer,
        private GraphMutationInputValidator $inputValidator,
        private GraphMutationService $graphMutations,
        private GraphPayloadMapper $graphPayloads,
    ) {}

    /**
     * @param  array{input: array{id: string, edge_type?: string, purpose?: string|null}}  $args
     * @return array<string, mixed>
     */
    public function __invoke(null $root, array $args, GraphQLContext $context): array
    {
        $actor = $this->authorizer->actor($context, 'edges:write');
        $input = $args['input'];
        $attributes = collect($input)->except('id')->all();
        $this->inputValidator->updateEdge($attributes);

        return $this->authorizer->mutate(function () use ($actor, $input, $attributes): array {
            $edge = $this->graphMutations->updateEdge($actor, $input['id'], $attributes);

            return $this->graphPayloads->edge($edge, $actor);
        });
    }
}
