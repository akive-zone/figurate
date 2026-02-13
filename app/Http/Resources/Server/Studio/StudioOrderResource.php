<?php

namespace App\Http\Resources\Server\Studio;

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
use App\Models\Server\Order as OrderModel;

#[ApiResource(
    shortName: 'StudioOrder',
    operations: [
        new GetCollection(
            uriTemplate: '/studio/orders',
            provider: CollectionProvider::class,
            stateOptions: new EloquentOptions(modelClass: OrderModel::class),
            security: "is_granted('viewAny')",
        ),
        new Get(
            uriTemplate: '/studio/orders/{id}',
            provider: ItemProvider::class,
            stateOptions: new EloquentOptions(modelClass: OrderModel::class),
            security: "is_granted('view', object)",
        ),
        new Post(
            uriTemplate: '/studio/orders',
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: OrderModel::class),
            security: "is_granted('create')",
        ),
        new Patch(
            uriTemplate: '/studio/orders/{id}',
            provider: ItemProvider::class,
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: OrderModel::class),
            security: "is_granted('update', object)",
        ),
    ],
)]
#[QueryParameter(key: 'status', filter: EqualsFilter::class, property: 'status')]
#[QueryParameter(key: 'order', filter: OrderFilter::class, properties: ['created_at' => 'created_at'])]
final class StudioOrderResource {}
