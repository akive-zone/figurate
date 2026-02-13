<?php

namespace App\Http\Resources\Server\Signal;

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
use App\Models\Server\Message as MessageModel;

#[ApiResource(
    shortName: 'SignalMessage',
    operations: [
        new GetCollection(
            uriTemplate: '/signal/messages',
            provider: CollectionProvider::class,
            stateOptions: new EloquentOptions(modelClass: MessageModel::class),
            security: "is_granted('viewAny')",
        ),
        new Get(
            uriTemplate: '/signal/messages/{id}',
            provider: ItemProvider::class,
            stateOptions: new EloquentOptions(modelClass: MessageModel::class),
            security: "is_granted('view', object)",
        ),
        new Post(
            uriTemplate: '/signal/messages',
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: MessageModel::class),
            security: "is_granted('create')",
        ),
        new Patch(
            uriTemplate: '/signal/messages/{id}',
            provider: ItemProvider::class,
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: MessageModel::class),
            security: "is_granted('update', object)",
        ),
    ],
)]
#[QueryParameter(key: 'messageable_type', filter: EqualsFilter::class, property: 'messageable_type')]
#[QueryParameter(key: 'messageable_id', filter: EqualsFilter::class, property: 'messageable_id')]
#[QueryParameter(key: 'senderable_type', filter: EqualsFilter::class, property: 'senderable_type')]
#[QueryParameter(key: 'senderable_id', filter: EqualsFilter::class, property: 'senderable_id')]
#[QueryParameter(key: 'body', filter: PartialSearchFilter::class, property: 'body')]
#[QueryParameter(key: 'order', filter: OrderFilter::class, properties: ['created_at' => 'created_at'])]
final class SignalMessageResource {}
