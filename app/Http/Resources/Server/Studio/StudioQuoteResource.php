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
use App\Models\Server\Quote as QuoteModel;

#[ApiResource(
    shortName: 'StudioQuote',
    operations: [
        new GetCollection(
            uriTemplate: '/studio/quotes',
            provider: CollectionProvider::class,
            stateOptions: new EloquentOptions(modelClass: QuoteModel::class),
            security: "is_granted('viewAny')",
        ),
        new Get(
            uriTemplate: '/studio/quotes/{id}',
            provider: ItemProvider::class,
            stateOptions: new EloquentOptions(modelClass: QuoteModel::class),
            security: "is_granted('view', object)",
        ),
        new Post(
            uriTemplate: '/studio/quotes',
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: QuoteModel::class),
            security: "is_granted('create')",
        ),
        new Patch(
            uriTemplate: '/studio/quotes/{id}',
            provider: ItemProvider::class,
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: QuoteModel::class),
            security: "is_granted('update', object)",
        ),
    ],
)]
#[QueryParameter(key: 'status', filter: EqualsFilter::class, property: 'status')]
#[QueryParameter(key: 'request_id', filter: EqualsFilter::class, property: 'request_id')]
#[QueryParameter(key: 'profile_id', filter: EqualsFilter::class, property: 'profile_id')]
#[QueryParameter(key: 'currency', filter: EqualsFilter::class, property: 'currency')]
#[QueryParameter(key: 'order', filter: OrderFilter::class, properties: ['created_at' => 'created_at'])]
final class StudioQuoteResource {}
