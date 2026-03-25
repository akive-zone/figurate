<?php

namespace App\Http\Resources\Server\Api\Platform;

use ApiPlatform\Laravel\Eloquent\Filter\EqualsFilter;
use ApiPlatform\Laravel\Eloquent\Filter\OrderFilter;
use ApiPlatform\Laravel\Eloquent\State\CollectionProvider;
use ApiPlatform\Laravel\Eloquent\State\ItemProvider;
use ApiPlatform\Laravel\Eloquent\State\Options as EloquentOptions;
use ApiPlatform\Laravel\Eloquent\State\PersistProcessor;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Models\Server\Space as SpaceModel;

#[ApiResource(
    shortName: 'Platform Space',
    operations: [
        new GetCollection(
            uriTemplate: '/platform/spaces',
            provider: CollectionProvider::class,
            stateOptions: new EloquentOptions(modelClass: SpaceModel::class),
            security: "is_granted('viewAny')",
        ),
        new Get(
            uriTemplate: '/platform/spaces/{id}',
            provider: ItemProvider::class,
            stateOptions: new EloquentOptions(modelClass: SpaceModel::class),
            security: "is_granted('view', object)",
        ),
        new Post(
            uriTemplate: '/platform/spaces',
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: SpaceModel::class),
            security: "is_granted('create')",
        ),
        new Patch(
            uriTemplate: '/platform/spaces/{id}',
            provider: ItemProvider::class,
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: SpaceModel::class),
            security: "is_granted('update', object)",
        ),
    ],
)]
#[QueryParameter(key: 'status', filter: EqualsFilter::class, property: 'status')]
#[QueryParameter(key: 'order', filter: OrderFilter::class, properties: ['created_at' => 'created_at'])]
final class SpaceResource {}
