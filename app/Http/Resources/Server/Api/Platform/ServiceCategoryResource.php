<?php

namespace App\Http\Resources\Server\Api\Platform;

use ApiPlatform\Laravel\Eloquent\Filter\PartialSearchFilter;
use ApiPlatform\Laravel\Eloquent\State\CollectionProvider;
use ApiPlatform\Laravel\Eloquent\State\ItemProvider;
use ApiPlatform\Laravel\Eloquent\State\Options as EloquentOptions;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Models\Server\ServiceCategory as ServiceCategoryModel;

#[ApiResource(
    shortName: 'Platform Service Category',
    operations: [
        new GetCollection(
            uriTemplate: '/platform/service_categories',
            provider: CollectionProvider::class,
            stateOptions: new EloquentOptions(modelClass: ServiceCategoryModel::class),
            security: "is_granted('viewAny')",
        ),
        new Get(
            uriTemplate: '/platform/service_categories/{id}',
            provider: ItemProvider::class,
            stateOptions: new EloquentOptions(modelClass: ServiceCategoryModel::class),
            security: "is_granted('view', object)",
        ),
    ],
)]
#[QueryParameter(key: 'name', filter: PartialSearchFilter::class, property: 'name')]
#[QueryParameter(key: 'slug', filter: PartialSearchFilter::class, property: 'slug')]
final class ServiceCategoryResource {}
