<?php

namespace App\Features\Actions\Conversation;

use App\Features\Actions\Conversation\Protocols\ChannelProtocol;
use App\Jobs\DeliverOutboxMessage;
use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use App\Models\Server\Outbox;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Collection;

class EnqueueThreadMessageOutbox
{
    /**
     * @return Collection<int, Outbox>
     */
    public function execute(Post $post): Collection
    {
        $thread = $this->resolveThread($post);
        if (! $thread) {
            return collect();
        }

        if (! $this->shouldFanOut($post)) {
            return collect();
        }

        $created = collect();

        foreach ($this->resolveFanoutTargets($thread, $post) as $target) {
            $idempotencyKey = sprintf(
                'outbound:%d:%s:%s:%s',
                $post->id,
                $target['protocol'],
                $target['provider'] ?? 'default',
                sha1($target['route_key'])
            );

            $outbox = Outbox::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'thread_id' => $thread->id,
                    'post_id' => $post->id,
                    'direction' => Outbox::DirectionOutbound,
                    'protocol' => $target['protocol'],
                    'provider' => $target['provider'],
                    'target' => $target['target'],
                    'status' => Outbox::StatusPending,
                    'attempts' => 0,
                    'available_at' => now(),
                    'payload' => is_array($target['payload'] ?? null)
                        ? $target['payload']
                        : $this->defaultOutboxPayload($thread, $post, $target),
                ],
            );

            if ($outbox->wasRecentlyCreated) {
                DeliverOutboxMessage::dispatch($outbox->id);
                $created->push($outbox);
            }
        }

        return $created;
    }

    protected function resolveThread(Post $post): ?Thread
    {
        $threadMorphClass = (new Thread)->getMorphClass();
        $postableType = is_string($post->postable_type) ? trim($post->postable_type) : '';

        if (! in_array($postableType, [$threadMorphClass, Thread::class], true)) {
            return null;
        }

        return Thread::query()->find($post->postable_id);
    }

    protected function shouldFanOut(Post $post): bool
    {
        $source = (string) data_get($post->meta, 'source', '');

        return $source !== '' && ! str_ends_with($source, '_inbound');
    }

    /**
     * @return list<array{
     *   protocol: string,
     *   provider: string|null,
     *   target: string,
     *   route_key: string,
     *   payload: array<string, mixed>
     * }>
     */
    protected function resolveFanoutTargets(Thread $thread, Post $post): array
    {
        $sender = $post->sender();
        $actorTargets = $thread->actors()
            ->whereIn('role', [ThreadActor::RoleMember, ThreadActor::RoleListener])
            ->where('status', ThreadActor::StatusActive)
            ->get()
            ->flatMap(function (ThreadActor $actor): array {
                if ($actor->actorable_type === User::class && $actor->actorable_id !== null) {
                    return [];
                }

                $config = is_array($actor->config) ? $actor->config : [];
                $targets = data_get($config, 'outbox.targets');

                if (! is_array($targets)) {
                    $singleProtocol = $this->normalizedProtocol(data_get($config, 'outbox.protocol') ?? data_get($config, 'protocol'));
                    $singleProvider = $this->normalizedProvider(data_get($config, 'outbox.provider') ?? data_get($config, 'provider'));
                    $singleTarget = $this->normalizedTarget(data_get($config, 'outbox.target') ?? data_get($config, 'target'));

                    if ($singleProtocol === null || $singleTarget === null) {
                        return [];
                    }

                    return [[
                        'protocol' => $singleProtocol,
                        'provider' => $singleProvider,
                        'target' => $singleTarget,
                        'route_key' => "{$singleProtocol}:".($singleProvider ?? 'default').":{$singleTarget}",
                    ]];
                }

                return collect($targets)
                    ->map(function (mixed $target): ?array {
                        if (! is_array($target)) {
                            return null;
                        }

                        if (($target['enabled'] ?? true) === false) {
                            return null;
                        }

                        $protocol = $this->normalizedProtocol($target['protocol'] ?? null);
                        $provider = $this->normalizedProvider($target['provider'] ?? null);
                        $endpoint = $this->normalizedTarget($target['target'] ?? null);

                        if ($protocol === null || $endpoint === null) {
                            return null;
                        }

                        return [
                            'protocol' => $protocol,
                            'provider' => $provider,
                            'target' => $endpoint,
                            'route_key' => "{$protocol}:".($provider ?? 'default').":{$endpoint}",
                        ];
                    })
                    ->filter(fn (mixed $target): bool => is_array($target))
                    ->values()
                    ->all();
            })
            ->values();

        $channelTargets = $thread->channelRelations()
            ->with('channel')
            ->where('kind', ChannelRelation::KindBind)
            ->where('status', Channel::StatusActive)
            ->get()
            ->map(function (ChannelRelation $binding) use ($post, $sender, $thread): ?array {
                $channel = $binding->channel;

                if (! $channel instanceof Channel) {
                    return null;
                }

                if (! in_array($channel->status, [Channel::StatusActive, null], true)) {
                    return null;
                }

                $bindingDirection = $this->normalizedDirection($binding->direction);
                $channelDirection = $this->normalizedDirection($channel->direction);

                if (! in_array($bindingDirection, [Channel::DirectionOutbound, Channel::DirectionBidirectional], true)) {
                    return null;
                }

                if (! in_array($channelDirection, [Channel::DirectionOutbound, Channel::DirectionBidirectional], true)) {
                    return null;
                }

                $bindingData = $this->arrayValue($binding->data);

                if ($this->bindingTargetsSender($bindingData, $sender)) {
                    return null;
                }

                $providerIdentifier = $this->normalizedTarget(data_get($bindingData, 'provider_identifier'));
                $config = $this->arrayValue(data_get($bindingData, 'config'));
                $bindingActorId = $this->normalizedScalar(data_get($bindingData, 'actor_id'));
                $bindingActorType = $this->normalizedString(data_get($bindingData, 'actor_type'));
                $bindingProviderKind = $this->normalizedString(data_get($bindingData, 'provider_kind'));
                $deliveryScope = $this->normalizedString(data_get($bindingData, 'delivery_scope'));
                $deliveryMode = $this->normalizedString(data_get($bindingData, 'delivery_mode'));
                $address = $this->arrayValue(data_get($bindingData, 'address'));
                $preferences = $this->arrayValue(data_get($bindingData, 'preferences'));

                return [
                    'protocol' => ChannelProtocol::Key,
                    'provider' => $this->normalizedProvider($channel->driver) ?? Channel::ProtocolGeneric,
                    'target' => $providerIdentifier ?? $channel->uuid,
                    'route_key' => 'channel_binding:'.$binding->id,
                    'payload' => [
                        'event' => 'thread.post.created',
                        'occurred_at' => optional($post->occurred_at ?? $post->created_at)?->toIso8601String(),
                        'channel' => [
                            'id' => $channel->id,
                            'uuid' => $channel->uuid,
                            'driver' => $channel->driver,
                            'name' => $channel->name,
                            'direction' => $channelDirection,
                            'status' => $channel->status,
                        ],
                        'binding' => [
                            'id' => $binding->id,
                            'kind' => $binding->kind,
                            'status' => $binding->status,
                            'direction' => $bindingDirection,
                            'provider_identifier' => $providerIdentifier,
                            'provider_kind' => $bindingProviderKind,
                            'delivery_scope' => $deliveryScope,
                            'delivery_mode' => $deliveryMode,
                            'actor_id' => $bindingActorId,
                            'actor_type' => $bindingActorType,
                            'address' => $address,
                            'preferences' => $preferences,
                            'config' => $config,
                            'data' => $bindingData,
                            'meta' => is_array($binding->meta) ? $binding->meta : [],
                        ],
                        'thread' => [
                            'id' => $thread->id,
                            'uuid' => $thread->uuid,
                            'purpose' => $thread->purpose,
                            'title' => $thread->title,
                            'phase' => $thread->phase,
                            'status' => $thread->status,
                        ],
                        'space' => $this->spacePayload($thread),
                        'post' => [
                            'id' => $post->id,
                            'ulid' => $post->ulid,
                            'type' => $post->type,
                            'tag' => $post->tag,
                            'text' => $post->text,
                            'data' => is_array($post->data) ? $post->data : [],
                            'attachments' => $post->attachments,
                            'meta' => is_array($post->meta) ? $post->meta : [],
                            'created_at' => optional($post->created_at)?->toIso8601String(),
                        ],
                        'sender' => $this->senderPayload($sender),
                        'recipients' => [[
                            'actor_id' => $bindingActorId,
                            'actor_type' => $bindingActorType,
                            'provider_identifier' => $providerIdentifier,
                            'provider_kind' => $bindingProviderKind,
                            'delivery_scope' => $deliveryScope,
                            'delivery_mode' => $deliveryMode,
                            'address' => $address,
                        ]],
                        'delivery' => [
                            'provider' => $this->normalizedProvider($channel->driver) ?? Channel::ProtocolGeneric,
                            'target' => $providerIdentifier ?? $channel->uuid,
                            'channel' => [
                                'id' => $channel->id,
                                'uuid' => $channel->uuid,
                                'driver' => $channel->driver,
                            ],
                            'binding' => [
                                'id' => $binding->id,
                                'provider_identifier' => $providerIdentifier,
                                'direction' => $bindingDirection,
                                'status' => $binding->status,
                                'config' => $config,
                                'kind' => $binding->kind,
                            ],
                        ],
                    ],
                ];
            })
            ->filter(fn (mixed $target): bool => is_array($target))
            ->values()
            ->all();

        return collect()
            ->merge($actorTargets)
            ->merge($channelTargets)
            ->unique(fn (array $target): string => (string) $target['route_key'])
            ->values()
            ->all();
    }

    protected function bindingTargetsSender(array $bindingData, ?EloquentModel $sender): bool
    {
        if (! $sender instanceof EloquentModel) {
            return false;
        }

        $bindingActorId = $this->normalizedScalar(data_get($bindingData, 'actor_id'));

        if ($bindingActorId === null || (string) $bindingActorId !== (string) $sender->getKey()) {
            return false;
        }

        $bindingActorType = $this->normalizedString(data_get($bindingData, 'actor_type'));

        return $bindingActorType === null || in_array($bindingActorType, [$sender->getMorphClass(), $sender::class], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function spacePayload(Thread $thread): ?array
    {
        $threadable = $thread->relationLoaded('threadable') ? $thread->threadable : $thread->threadable()->first();

        if (! $threadable instanceof Space) {
            return null;
        }

        return [
            'id' => $threadable->id,
            'uuid' => $threadable->uuid,
            'status' => $threadable->status,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function senderPayload(?EloquentModel $sender): ?array
    {
        if (! $sender instanceof EloquentModel) {
            return null;
        }

        return [
            'type' => $sender->getMorphClass(),
            'id' => $sender->getKey(),
            'uuid' => $this->normalizedString(data_get($sender, 'uuid')),
            'display_name' => $this->normalizedString(data_get($sender, 'name'))
                ?? $this->normalizedString(data_get($sender, 'title')),
        ];
    }

    /**
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    protected function defaultOutboxPayload(Thread $thread, Post $post, array $target): array
    {
        return [
            'message' => [
                'id' => $post->id,
                'text' => $post->text,
                'source' => data_get($post->meta, 'source'),
                'meta' => is_array($post->meta) ? $post->meta : [],
                'created_at' => optional($post->created_at)?->toIso8601String(),
            ],
            'delivery' => [
                'provider' => $target['provider'] ?? null,
                'target' => $target['target'] ?? null,
            ],
            'thread' => [
                'id' => $thread->id,
                'uuid' => $thread->uuid,
            ],
        ];
    }

    protected function normalizedProtocol(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $protocol = strtolower(trim($value));

        if ($protocol === '') {
            return null;
        }

        return $protocol;
    }

    protected function normalizedTarget(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $target = trim($value);

        if ($target === '') {
            return null;
        }

        return $target;
    }

    protected function normalizedProvider(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $provider = trim($value);

        if ($provider === '') {
            return null;
        }

        return $provider;
    }

    protected function normalizedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    protected function normalizedScalar(mixed $value): string|int|null
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        return ctype_digit($normalized) ? (int) $normalized : $normalized;
    }

    protected function normalizedDirection(mixed $value): string
    {
        if (! is_string($value)) {
            return Channel::DirectionBidirectional;
        }

        $direction = strtolower(trim($value));

        return in_array($direction, [Channel::DirectionInbound, Channel::DirectionOutbound, Channel::DirectionBidirectional], true)
            ? $direction
            : Channel::DirectionBidirectional;
    }

    /**
     * @return array<string, mixed>
     */
    protected function arrayValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
