<?php

namespace App\Http\Resources\Server\Studio;

use ApiPlatform\Laravel\Eloquent\Filter\DateFilter;
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
    shortName: 'StudioDispute',
    operations: [
        new GetCollection(
            uriTemplate: '/studio/disputes',
            provider: CollectionProvider::class,
            stateOptions: new EloquentOptions(modelClass: DisputeModel::class),
            security: "is_granted('viewAny')",
        ),
        new Get(
            uriTemplate: '/studio/disputes/{id}',
            provider: ItemProvider::class,
            stateOptions: new EloquentOptions(modelClass: DisputeModel::class),
            security: "is_granted('view', object)",
        ),
        new Post(
            uriTemplate: '/studio/disputes',
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: DisputeModel::class),
            security: "is_granted('create')",
        ),
        new Patch(
            uriTemplate: '/studio/disputes/{id}',
            provider: ItemProvider::class,
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: DisputeModel::class),
            security: "is_granted('update', object)",
        ),
    ],
)]
#[QueryParameter(key: 'order_id', filter: EqualsFilter::class, property: 'order_id')]
#[QueryParameter(key: 'status', filter: EqualsFilter::class, property: 'status')]
#[QueryParameter(key: 'opened_by', filter: EqualsFilter::class, property: 'opened_by')]
#[QueryParameter(key: 'resolved_by', filter: EqualsFilter::class, property: 'resolved_by')]
#[QueryParameter(key: 'resolved_at', filter: DateFilter::class, property: 'resolved_at')]
#[QueryParameter(key: 'order', filter: OrderFilter::class, properties: ['created_at' => 'created_at'])]
final class StudioDisputeResource {}
