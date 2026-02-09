<?php

namespace App\Http\Resources\Server\Studio;

use ApiPlatform\Laravel\Eloquent\Filter\DateFilter;
use ApiPlatform\Laravel\Eloquent\Filter\EqualsFilter;
use ApiPlatform\Laravel\Eloquent\Filter\OrderFilter;
use ApiPlatform\Laravel\Eloquent\Filter\PartialSearchFilter;
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
use App\Models\Server\Profile as ProfileModel;

#[ApiResource(
    shortName: 'StudioProfile',
    operations: [
        new GetCollection(
            uriTemplate: '/studio/profiles',
            provider: CollectionProvider::class,
            stateOptions: new EloquentOptions(modelClass: ProfileModel::class),
            security: "is_granted('viewAny')",
        ),
        new Get(
            uriTemplate: '/studio/profiles/{id}',
            provider: ItemProvider::class,
            stateOptions: new EloquentOptions(modelClass: ProfileModel::class),
            security: "is_granted('view', object)",
        ),
        new Post(
            uriTemplate: '/studio/profiles',
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: ProfileModel::class),
            security: "is_granted('create')",
        ),
        new Patch(
            uriTemplate: '/studio/profiles/{id}',
            provider: ItemProvider::class,
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: ProfileModel::class),
            security: "is_granted('update', object)",
        ),
    ],
)]
#[QueryParameter(key: 'user_id', filter: EqualsFilter::class, property: 'user_id')]
#[QueryParameter(key: 'status', filter: EqualsFilter::class, property: 'status')]
#[QueryParameter(key: 'display_name', filter: PartialSearchFilter::class, property: 'display_name')]
#[QueryParameter(key: 'location', filter: PartialSearchFilter::class, property: 'location')]
#[QueryParameter(key: 'approved_at', filter: DateFilter::class, property: 'approved_at')]
#[QueryParameter(key: 'order', filter: OrderFilter::class, properties: ['created_at' => 'created_at'])]
final class StudioProfileResource {}
