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
use App\Models\Server\Rating as RatingModel;

#[ApiResource(
    shortName: 'StudioRating',
    operations: [
        new GetCollection(
            uriTemplate: '/studio/ratings',
            provider: CollectionProvider::class,
            stateOptions: new EloquentOptions(modelClass: RatingModel::class),
            security: "is_granted('viewAny')",
        ),
        new Get(
            uriTemplate: '/studio/ratings/{id}',
            provider: ItemProvider::class,
            stateOptions: new EloquentOptions(modelClass: RatingModel::class),
            security: "is_granted('view', object)",
        ),
        new Post(
            uriTemplate: '/studio/ratings',
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: RatingModel::class),
            security: "is_granted('create')",
        ),
    ],
)]
#[QueryParameter(key: 'order_id', filter: EqualsFilter::class, property: 'order_id')]
#[QueryParameter(key: 'rater_id', filter: EqualsFilter::class, property: 'rater_id')]
#[QueryParameter(key: 'rated_id', filter: EqualsFilter::class, property: 'rated_id')]
#[QueryParameter(key: 'score', filter: EqualsFilter::class, property: 'score')]
#[QueryParameter(key: 'order', filter: OrderFilter::class, properties: ['created_at' => 'created_at'])]
final class StudioRatingResource {}
