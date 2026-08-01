<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\GraphQL\Support\GraphMutationInputValidator;
use App\GraphQL\Support\GraphQLAuthorizer;
use App\Support\Graph\GraphMutationService;
use App\Support\Graph\GraphPayloadMapper;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final readonly class UpdateGraphNode
{
    public function __construct(
        private GraphQLAuthorizer $authorizer,
        private GraphMutationInputValidator $inputValidator,
        private GraphMutationService $graphMutations,
        private GraphPayloadMapper $graphPayloads,
    ) {}

    /**
     * @param  array{input: array{type: string, id: string, attributes: array<string, mixed>}}  $args
     * @return array<string, mixed>
     */
    public function __invoke(null $root, array $args, GraphQLContext $context): array
    {
        $actor = $this->authorizer->actor($context, 'nodes:write');
        $input = $args['input'];
        $this->inputValidator->updateNode($input['type'], $input['attributes']);

        return $this->authorizer->mutate(function () use ($actor, $input): array {
            $node = $this->graphMutations->updateNode(
                $actor,
                $input['type'],
                $input['id'],
                $input['attributes'],
            );

            return $this->graphPayloads->node($node, $actor);
        });
    }
}
