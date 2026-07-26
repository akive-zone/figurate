<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireApiAbility;
use App\TokenAbility;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class OpenApiController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Figurate API',
                'version' => (string) config('app.version', 'unreleased'),
                'description' => 'API-first access to users, hierarchical nodes, graph edges, forms, invocations, credentials, and channels.',
            ],
            'servers' => [
                ['url' => url('/api')],
            ],
            'tags' => collect([
                'Auth',
                'Credentials',
                'Forms',
                'Nodes',
                'Edges',
                'Spaces',
                'Threads',
                'Posts',
                'Channels',
            ])->map(fn (string $name): array => ['name' => $name])->all(),
            'paths' => $this->paths(),
            'components' => $this->components(),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function paths(): array
    {
        $paths = [];

        foreach (Route::getRoutes() as $route) {
            if (
                ! $route instanceof LaravelRoute
                || ! is_string($route->getName())
                || ! str_starts_with($route->getName(), 'api.')
            ) {
                continue;
            }

            $path = substr($route->uri(), strlen('api'));
            $path = $path === '' ? '/' : '/'.ltrim($path, '/');

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $paths[$path][strtolower($method)] = $this->operation($route, strtolower($method));
            }
        }

        ksort($paths);

        return $paths;
    }

    /**
     * @return array<string, mixed>
     */
    protected function operation(LaravelRoute $route, string $method): array
    {
        $name = $route->getName() ?? Str::slug($method.' '.$route->uri(), '.');
        $shortName = Str::after($name, 'api.');
        $tag = Str::headline(Str::before($shortName, '.'));
        $operation = [
            'tags' => [$tag],
            'summary' => Str::headline($shortName),
            'operationId' => str_replace('.', '_', $name),
            'parameters' => $this->parameters($route, $name),
            'responses' => $this->responses($method, $name),
        ];

        if (! in_array($name, ['api.openapi', 'api.auth.register', 'api.auth.login'], true)) {
            $operation['security'] = [['bearerAuth' => []]];
        }

        $ability = collect($route->gatherMiddleware())
            ->first(fn (string $middleware): bool => str_starts_with($middleware, 'api.ability:')
                || str_starts_with($middleware, RequireApiAbility::class.':'));

        if (is_string($ability)) {
            $prefix = str_starts_with($ability, 'api.ability:')
                ? 'api.ability:'
                : RequireApiAbility::class.':';
            $operation['x-required-ability'] = Str::after($ability, $prefix);
        }

        $schema = $this->requestSchema($name);
        if ($schema !== null && in_array($method, ['post', 'put', 'patch'], true)) {
            $operation['requestBody'] = [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => "#/components/schemas/{$schema}"],
                    ],
                ],
            ];
        }

        return $operation;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function parameters(LaravelRoute $route, string $name): array
    {
        $parameters = collect($route->parameterNames())
            ->map(fn (string $parameter): array => [
                'name' => $parameter,
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => 'string',
                    'description' => $this->identifierDescription($parameter),
                ],
            ]);

        if (str_ends_with($name, '.nodes.index')) {
            $parameters->push(
                [
                    'name' => 'per_page',
                    'in' => 'query',
                    'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25],
                ],
                [
                    'name' => 'cursor',
                    'in' => 'query',
                    'schema' => ['type' => 'string'],
                ],
            );
        }

        if (in_array($name, ['api.edges.index', 'api.form.edges.index'], true)) {
            foreach ([
                ['node_type', true, ['type' => 'string', 'enum' => ['space', 'thread', 'post']]],
                ['node_id', true, ['type' => 'string']],
                ['direction', false, ['type' => 'string', 'enum' => ['incoming', 'outgoing', 'both']]],
                ['edge_type', false, ['type' => 'string']],
                ['target_type', false, ['type' => 'string', 'enum' => ['space', 'thread', 'post']]],
                ['depth', false, ['type' => 'integer', 'minimum' => 1]],
                ['limit', false, ['type' => 'integer', 'minimum' => 1]],
            ] as [$parameter, $required, $schema]) {
                $parameters->push([
                    'name' => $parameter,
                    'in' => 'query',
                    'required' => $required,
                    'schema' => $schema,
                ]);
            }
        }

        if ($name === 'api.spaces.index') {
            $parameters->push([
                'name' => 'status',
                'in' => 'query',
                'schema' => ['type' => 'string'],
            ]);
        }

        if (in_array($name, [
            'api.form.store',
            'api.form.nodes.store',
            'api.form.edges.store',
            'api.nodes.store',
            'api.edges.store',
            'api.spaces.store',
        ], true)) {
            $parameters->push([
                '$ref' => '#/components/parameters/IdempotencyKey',
            ]);
        }

        return $parameters->all();
    }

    protected function identifierDescription(string $parameter): string
    {
        return match ($parameter) {
            'space', 'thread', 'channel' => 'Public UUID.',
            'post', 'edge', 'credential', 'route', 'address', 'connection' => 'Public ULID.',
            'invocation' => 'Invocation identifier.',
            'type' => 'Node type: space, thread, or post.',
            default => 'Public identifier.',
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function responses(string $method, string $name): array
    {
        if ($method === 'delete') {
            return [
                '204' => ['description' => 'Deleted.'],
                '401' => ['$ref' => '#/components/responses/Unauthenticated'],
                '403' => ['$ref' => '#/components/responses/Forbidden'],
                '404' => ['$ref' => '#/components/responses/NotFound'],
            ];
        }

        $successStatus = match ($name) {
            'api.form.store' => '202',
            'api.auth.login', 'api.auth.register' => '200',
            default => $method === 'post' ? '201' : '200',
        };

        return [
            $successStatus => [
                'description' => 'Successful response.',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/Envelope'],
                    ],
                ],
            ],
            '401' => ['$ref' => '#/components/responses/Unauthenticated'],
            '403' => ['$ref' => '#/components/responses/Forbidden'],
            '404' => ['$ref' => '#/components/responses/NotFound'],
            '422' => ['$ref' => '#/components/responses/ValidationError'],
        ];
    }

    protected function requestSchema(string $name): ?string
    {
        return match ($name) {
            'api.auth.register' => 'RegisterRequest',
            'api.auth.login' => 'LoginRequest',
            'api.credentials.store' => 'ApiCredentialRequest',
            'api.form.store' => 'FormRequest',
            'api.nodes.store', 'api.form.nodes.store' => 'NodeCreateRequest',
            'api.nodes.update', 'api.form.nodes.update' => 'NodeUpdateRequest',
            'api.edges.store', 'api.form.edges.store' => 'EdgeCreateRequest',
            'api.edges.update', 'api.form.edges.update' => 'EdgeUpdateRequest',
            'api.spaces.store' => 'SpaceCreateRequest',
            'api.channels.store',
            'api.channels.update' => 'ChannelRequest',
            'api.channels.connections.store',
            'api.channels.connections.update' => 'ChannelConnectionRequest',
            'api.channels.routes.store',
            'api.channels.routes.update' => 'ChannelRouteRequest',
            'api.channels.routes.addresses.store',
            'api.channels.routes.addresses.update' => 'ChannelAddressRequest',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function components(): array
    {
        $object = ['type' => 'object', 'additionalProperties' => true];
        $nodeTypes = ['type' => 'string', 'enum' => ['space', 'thread', 'post']];
        $publicId = ['type' => 'string'];

        return [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'Sanctum token',
                ],
            ],
            'parameters' => [
                'IdempotencyKey' => [
                    'name' => 'Idempotency-Key',
                    'in' => 'header',
                    'required' => false,
                    'description' => 'Client-generated key for safely retrying create requests.',
                    'schema' => ['type' => 'string', 'maxLength' => 255],
                ],
            ],
            'responses' => [
                'Unauthenticated' => ['description' => 'Authentication is required.'],
                'Forbidden' => ['description' => 'The credential lacks access or the required ability.'],
                'NotFound' => ['description' => 'The requested resource was not found.'],
                'ValidationError' => ['description' => 'The request failed validation.'],
            ],
            'schemas' => [
                'Envelope' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => [],
                        'meta' => $object,
                    ],
                ],
                'RegisterRequest' => [
                    'type' => 'object',
                    'required' => ['name', 'email', 'password', 'password_confirmation'],
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'password' => ['type' => 'string', 'format' => 'password'],
                        'password_confirmation' => ['type' => 'string', 'format' => 'password'],
                    ],
                ],
                'LoginRequest' => [
                    'type' => 'object',
                    'required' => ['email', 'password'],
                    'properties' => [
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'password' => ['type' => 'string', 'format' => 'password'],
                    ],
                ],
                'ApiCredentialRequest' => [
                    'type' => 'object',
                    'required' => ['name', 'abilities'],
                    'properties' => [
                        'name' => ['type' => 'string', 'maxLength' => 100],
                        'abilities' => [
                            'type' => 'array',
                            'items' => ['$ref' => '#/components/schemas/ApiAbility'],
                            'uniqueItems' => true,
                        ],
                        'expires_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    ],
                ],
                'ApiAbility' => [
                    'type' => 'string',
                    'enum' => TokenAbility::thirdPartyValues(),
                ],
                'FormRequest' => $object,
                'NodeReference' => [
                    'type' => 'object',
                    'required' => ['type', 'id'],
                    'properties' => [
                        'type' => $nodeTypes,
                        'id' => $publicId,
                    ],
                ],
                'NodeCreateRequest' => [
                    'type' => 'object',
                    'required' => ['type'],
                    'properties' => [
                        'type' => $nodeTypes,
                        'parent' => [
                            'anyOf' => [
                                ['$ref' => '#/components/schemas/NodeReference'],
                                ['type' => 'null'],
                            ],
                        ],
                        'attributes' => $object,
                    ],
                ],
                'NodeUpdateRequest' => [
                    'type' => 'object',
                    'required' => ['attributes'],
                    'properties' => ['attributes' => $object],
                ],
                'EdgeCreateRequest' => [
                    'type' => 'object',
                    'required' => ['source_type', 'source_id', 'target_type', 'target_id', 'edge_type'],
                    'properties' => [
                        'source_type' => $nodeTypes,
                        'source_id' => $publicId,
                        'target_type' => $nodeTypes,
                        'target_id' => $publicId,
                        'edge_type' => ['type' => 'string'],
                        'purpose' => ['type' => ['string', 'null']],
                    ],
                ],
                'EdgeUpdateRequest' => [
                    'type' => 'object',
                    'properties' => [
                        'edge_type' => ['type' => 'string'],
                        'purpose' => ['type' => ['string', 'null']],
                    ],
                    'minProperties' => 1,
                ],
                'SpaceCreateRequest' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string'],
                    ],
                ],
                'ChannelRequest' => $object,
                'ChannelConnectionRequest' => $object,
                'ChannelRouteRequest' => $object,
                'ChannelAddressRequest' => $object,
            ],
        ];
    }
}
