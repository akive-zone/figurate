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
use App\Models\Server\Channel as ChannelModel;

#[ApiResource(
    shortName: 'StudioChannel',
    operations: [
        new GetCollection(
            uriTemplate: '/studio/channels',
            provider: CollectionProvider::class,
            stateOptions: new EloquentOptions(modelClass: ChannelModel::class),
            security: "is_granted('viewAny')",
        ),
        new Get(
            uriTemplate: '/studio/channels/{id}',
            provider: ItemProvider::class,
            stateOptions: new EloquentOptions(modelClass: ChannelModel::class),
            security: "is_granted('view', object)",
        ),
        new Post(
            uriTemplate: '/studio/channels',
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: ChannelModel::class),
            security: "is_granted('create')",
        ),
        new Patch(
            uriTemplate: '/studio/channels/{id}',
            provider: ItemProvider::class,
            processor: PersistProcessor::class,
            stateOptions: new EloquentOptions(modelClass: ChannelModel::class),
            security: "is_granted('update', object)",
        ),
    ],
)]
#[QueryParameter(key: 'requester_id', filter: EqualsFilter::class, property: 'requester_id')]
#[QueryParameter(key: 'profile_id', filter: EqualsFilter::class, property: 'profile_id')]
#[QueryParameter(key: 'status', filter: EqualsFilter::class, property: 'status')]
#[QueryParameter(key: 'order', filter: OrderFilter::class, properties: ['last_message_at' => 'last_message_at', 'created_at' => 'created_at'])]
final class StudioChannelResource {}
