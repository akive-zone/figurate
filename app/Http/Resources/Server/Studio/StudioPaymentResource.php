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
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\Models\Server\Payment as PaymentModel;

#[ApiResource(
    shortName: 'StudioPayment',
    operations: [
        new GetCollection(
            uriTemplate: '/studio/payments',
            provider: CollectionProvider::class,
            stateOptions: new EloquentOptions(modelClass: PaymentModel::class),
            security: "is_granted('viewAny')",
        ),
        new Get(
            uriTemplate: '/studio/payments/{id}',
            provider: ItemProvider::class,
            stateOptions: new EloquentOptions(modelClass: PaymentModel::class),
            security: "is_granted('view', object)",
        ),
        new Post(
            uriTemplate: '/studio/payments',
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: PaymentModel::class),
            security: "is_granted('create')",
        ),
    ],
)]
#[QueryParameter(key: 'status', filter: EqualsFilter::class, property: 'status')]
#[QueryParameter(key: 'order', filter: OrderFilter::class, properties: ['created_at' => 'created_at'])]
final class StudioPaymentResource {}
