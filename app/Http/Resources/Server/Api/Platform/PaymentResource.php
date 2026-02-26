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
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Models\Server\Fulfillment\Payment as PaymentModel;

#[ApiResource(
    shortName: 'Platform Payment',
    operations: [
        new GetCollection(
            uriTemplate: '/platform/payments',
            provider: CollectionProvider::class,
            stateOptions: new EloquentOptions(modelClass: PaymentModel::class),
            security: "is_granted('viewAny')",
        ),
        new Get(
            uriTemplate: '/platform/payments/{id}',
            provider: ItemProvider::class,
            stateOptions: new EloquentOptions(modelClass: PaymentModel::class),
            security: "is_granted('view', object)",
        ),
        new Post(
            uriTemplate: '/platform/payments',
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: PaymentModel::class),
            security: "is_granted('create')",
        ),
    ],
)]
#[QueryParameter(key: 'status', filter: EqualsFilter::class, property: 'status')]
#[QueryParameter(key: 'order', filter: OrderFilter::class, properties: ['created_at' => 'created_at'])]
final class PaymentResource {}
