<?php

namespace App\Features\Actions\Chat;

use App\Events\Server\Channels\PreparingChannelOutboxPayload;
use App\Features\Actions\Chat\Protocols\ChannelProtocol;
use App\Jobs\DeliverOutboxMessage;
use App\Models\Server\Channel;
use App\Models\Server\ChannelAddress;
use App\Models\Server\ChannelRoute;
use App\Models\Server\Outbox;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
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
        $channelTargets = $this->resolveChannelAddresses($thread)
            ->map(function (ChannelAddress $address) use ($post, $sender, $thread): ?array {
                $route = $address->route;

                if (! $route instanceof ChannelRoute) {
                    return null;
                }

                $channel = $route->channel;

                if (! $channel instanceof Channel) {
                    return null;
                }

                if (! in_array($channel->status, [Channel::StatusActive, null], true)) {
                    return null;
                }

                if (! in_array($route->status, [Channel::StatusActive, null], true)) {
                    return null;
                }

                if (! in_array($address->status, [Channel::StatusActive, null], true)) {
                    return null;
                }

                $channelDirection = $this->normalizedDirection($channel->direction);
                $routeDirection = $this->normalizedDirection($route->direction);
                $addressDirection = $this->normalizedDirection($address->direction);

                if (! in_array($channelDirection, [Channel::DirectionOutbound, Channel::DirectionBidirectional], true)) {
                    return null;
                }

                if (! in_array($routeDirection, [Channel::DirectionOutbound, Channel::DirectionBidirectional], true)) {
                    return null;
                }

                if (! in_array($addressDirection, [Channel::DirectionOutbound, Channel::DirectionBidirectional], true)) {
                    return null;
                }

                if ($this->addressTargetsSender($address, $sender)) {
                    return null;
                }

                $routeConfig = $this->arrayValue($route->config);
                $routeData = $this->arrayValue($route->data);
                $routeMeta = $this->arrayValue($route->meta);
                $addressData = $this->arrayValue($address->data);
                $addressMeta = $this->arrayValue($address->meta);
                $target = $this->normalizedTarget($address->target);

                if ($target === null) {
                    return null;
                }

                $provider = $this->normalizedProvider($address->provider)
                    ?? $this->normalizedProvider($channel->driver)
                    ?? Channel::ProtocolGeneric;
                $addressable = $address->addressable;
                $payload = [
                    'event' => 'thread.post.created',
                    'occurred_at' => optional($post->occurred_at ?? $post->created_at)?->toIso8601String(),
                    'channel' => [
                        'id' => $channel->uuid,
                        'driver' => $channel->driver,
                        'name' => $channel->name,
                        'direction' => $channelDirection,
                        'status' => $channel->status,
                    ],
                    'route' => [
                        'id' => $route->ulid,
                        'name' => $route->name,
                        'label' => $route->label,
                        'status' => $route->status,
                        'direction' => $routeDirection,
                        'config' => $routeConfig,
                        'data' => $routeData,
                        'meta' => $routeMeta,
                    ],
                    'address' => [
                        'id' => $address->ulid,
                        'label' => $address->label,
                        'provider' => $provider,
                        'target' => $target,
                        'target_type' => $address->target_type,
                        'status' => $address->status,
                        'direction' => $addressDirection,
                        'addressable' => [
                            'type' => $addressable instanceof EloquentModel ? strtolower(class_basename($addressable)) : null,
                            'id' => $addressable instanceof EloquentModel ? $this->publicIdentifier($addressable) : null,
                        ],
                        'data' => $addressData,
                        'meta' => $addressMeta,
                    ],
                    'thread' => [
                        'id' => $thread->uuid,
                        'purpose' => $thread->purpose,
                        'title' => $thread->title,
                        'phase' => $thread->phase,
                        'status' => $thread->status,
                    ],
                    'space' => $this->spacePayload($thread),
                    'post' => [
                        'id' => $post->ulid,
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
                        'address_id' => $address->ulid,
                        'provider' => $provider,
                        'target' => $target,
                        'target_type' => $address->target_type,
                    ]],
                    'delivery' => [
                        'provider' => $provider,
                        'target' => $target,
                        'channel' => [
                            'id' => $channel->uuid,
                            'driver' => $channel->driver,
                        ],
                        'route' => [
                            'id' => $route->ulid,
                            'name' => $route->name,
                            'direction' => $routeDirection,
                            'status' => $route->status,
                            'config' => $routeConfig,
                        ],
                        'address' => [
                            'id' => $address->ulid,
                            'direction' => $addressDirection,
                            'status' => $address->status,
                            'provider' => $provider,
                            'target' => $target,
                            'target_type' => $address->target_type,
                            'data' => $addressData,
                        ],
                    ],
                ];
                $event = new PreparingChannelOutboxPayload($post, $channel, $route, $address, $payload);
                event($event);

                return [
                    'protocol' => ChannelProtocol::Key,
                    'provider' => $provider,
                    'target' => $target,
                    'route_key' => 'channel_address:'.$address->id,
                    'payload' => $event->payload,
                ];
            })
            ->filter(fn (mixed $target): bool => is_array($target))
            ->values()
            ->all();

        return collect()
            ->merge($channelTargets)
            ->unique(fn (array $target): string => (string) $target['route_key'])
            ->values()
            ->all();
    }

    protected function addressTargetsSender(ChannelAddress $address, ?EloquentModel $sender): bool
    {
        if (! $sender instanceof EloquentModel) {
            return false;
        }

        $addressable = $address->addressable;

        if (! $addressable instanceof EloquentModel) {
            return false;
        }

        return (string) $addressable->getKey() === (string) $sender->getKey()
            && in_array($addressable->getMorphClass(), [$sender->getMorphClass(), $sender::class], true);
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
            'id' => $threadable->uuid,
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
            'id' => $this->publicIdentifier($sender),
            'display_name' => $this->normalizedString(data_get($sender, 'name'))
                ?? $this->normalizedString(data_get($sender, 'title')),
        ];
    }

    /**
     * @return Collection<int, ChannelAddress>
     */
    protected function resolveChannelAddresses(Thread $thread): Collection
    {
        $addresses = $thread->channelAddresses()
            ->with(['route.channel', 'addressable'])
            ->where('status', Channel::StatusActive)
            ->get();

        $threadable = $thread->relationLoaded('threadable') ? $thread->threadable : $thread->threadable()->first();

        if ($threadable instanceof Space) {
            $addresses = $addresses->merge(
                $threadable->channelAddresses()
                    ->with(['route.channel', 'addressable'])
                    ->where('status', Channel::StatusActive)
                    ->get()
            );
        }

        return $addresses
            ->filter(fn (mixed $address): bool => $address instanceof ChannelAddress)
            ->unique(fn (ChannelAddress $address): int => $address->id)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    protected function defaultOutboxPayload(Thread $thread, Post $post, array $target): array
    {
        return [
            'message' => [
                'id' => $post->ulid,
                'text' => $post->text,
                'source' => data_get($post->meta, 'source'),
                'meta' => is_array($post->meta) ? $post->meta : [],
                'created_at' => optional($post->created_at)?->toIso8601String(),
            ],
            'event' => 'thread.post.created',
            'delivery' => [
                'provider' => $target['provider'] ?? null,
                'target' => $target['target'] ?? null,
            ],
            'thread' => [
                'id' => $thread->uuid,
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

    protected function publicIdentifier(EloquentModel $model): mixed
    {
        $uuid = $model->getAttribute('uuid');

        if (is_string($uuid) && $uuid !== '') {
            return $uuid;
        }

        return $model->getKey();
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
