<?php

namespace App\Http\Resources\Server\Signal;

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
use App\Models\Server\Assessment as AssessmentModel;

#[ApiResource(
    shortName: 'SignalAssessment',
    operations: [
        new GetCollection(
            uriTemplate: '/signal/assessments',
            provider: CollectionProvider::class,
            stateOptions: new EloquentOptions(modelClass: AssessmentModel::class),
            security: "is_granted('viewAny')",
        ),
        new Get(
            uriTemplate: '/signal/assessments/{id}',
            provider: ItemProvider::class,
            stateOptions: new EloquentOptions(modelClass: AssessmentModel::class),
            security: "is_granted('view', object)",
        ),
        new Post(
            uriTemplate: '/signal/assessments',
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: AssessmentModel::class),
            security: "is_granted('create')",
        ),
        new Patch(
            uriTemplate: '/signal/assessments/{id}',
            provider: ItemProvider::class,
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: AssessmentModel::class),
            security: "is_granted('update', object)",
        ),
    ],
)]
#[QueryParameter(key: 'status', filter: EqualsFilter::class, property: 'status')]
#[QueryParameter(key: 'order_id', filter: EqualsFilter::class, property: 'order_id')]
#[QueryParameter(key: 'acknowledged_at', filter: DateFilter::class, property: 'acknowledged_at')]
#[QueryParameter(key: 'order', filter: OrderFilter::class, properties: ['created_at' => 'created_at'])]
final class SignalAssessmentResource {}
