<?php

namespace App\Http\Resources\Server\Signal;

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
use App\Models\Server\Dispute as DisputeModel;

#[ApiResource(
    shortName: 'SignalDispute',
    operations: [
        new GetCollection(
            uriTemplate: '/signal/disputes',
            provider: CollectionProvider::class,
            stateOptions: new EloquentOptions(modelClass: DisputeModel::class),
            security: "is_granted('viewAny')",
        ),
        new Get(
            uriTemplate: '/signal/disputes/{id}',
            provider: ItemProvider::class,
            stateOptions: new EloquentOptions(modelClass: DisputeModel::class),
            security: "is_granted('view', object)",
        ),
        new Post(
            uriTemplate: '/signal/disputes',
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: DisputeModel::class),
            security: "is_granted('create')",
        ),
        new Patch(
            uriTemplate: '/signal/disputes/{id}',
            provider: ItemProvider::class,
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: DisputeModel::class),
            security: "is_granted('update', object)",
        ),
    ],
)]
#[QueryParameter(key: 'status', filter: EqualsFilter::class, property: 'status')]
#[QueryParameter(key: 'phase', filter: EqualsFilter::class, property: 'phase')]
#[QueryParameter(key: 'order', filter: OrderFilter::class, properties: ['created_at' => 'created_at'])]
final class SignalDisputeResource {}
