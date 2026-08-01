<?php

namespace App\GraphQL\Support;

use App\Models\Server\User;
use App\Support\Auth\ApiAbilityGate;
use GraphQL\Error\UserError;
use Illuminate\Auth\AuthenticationException;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class GraphQLAuthorizer
{
    public function __construct(protected ApiAbilityGate $apiAbilities) {}

    public function actor(GraphQLContext $context, string $ability): User
    {
        $actor = $context->user();

        if (! $actor instanceof User) {
            throw new AuthenticationException;
        }

        if (! $this->apiAbilities->allows($actor, $ability)) {
            throw new AuthorizationException(
                "The API credential does not have the required {$ability} ability.",
            );
        }

        return $actor;
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $operation
     * @return TResult
     */
    public function mutate(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (HttpExceptionInterface $exception) {
            throw new UserError($exception->getMessage(), previous: $exception);
        }
    }
}
