<?php

use Nuwave\Lighthouse\Http\Middleware\AcceptJson;

return [
    'schema_path' => resource_path('graphql/schema.graphql'),

    'route' => [
        'uri' => '/api/graphql',
        'name' => 'graphql',
        'middleware' => [
            AcceptJson::class,
            'auth:sanctum,passport',
        ],
    ],
];
