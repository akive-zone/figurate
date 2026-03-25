<?php

namespace App\Features\Actions\Conversation;

use App\Features\Actions\Conversation\Protocols\ChannelProtocol;
use App\Jobs\DeliverOutboxMessage;
use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use App\Models\Server\Outbox;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
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

        foreach ($this->resolveFanoutTargets($thread) as $target) {
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
                    'payload' => [
                        'message' => [
                            'id' => $post->id,
                            'text' => $post->text,
                            'source' => data_get($post->meta, 'source'),
                            'meta' => is_array($post->meta) ? $post->meta : [],
                            'created_at' => optional($post->created_at)?->toIso8601String(),
                        ],
                        'delivery' => [
                            'provider' => $target['provider'],
                            'target' => $target['target'],
                        ],
                        'thread' => [
                            'id' => $thread->id,
                            'uuid' => $thread->uuid,
                        ],
                    ],
                ],
            );

            if (isset($target['channel']) && is_array($target['channel'])) {
                $deliveryPayload = is_array($outbox->payload) ? $outbox->payload : [];
                $deliveryPayload['delivery']['channel'] = $target['channel'];
                $deliveryPayload['delivery']['binding'] = $target['binding'] ?? [];
                $outbox->forceFill([
                    'payload' => $deliveryPayload,
                ])->save();
            }

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
     *   channel?: array{uuid: string, id: int, driver: string},
     *   binding?: array{provider_identifier: string|null, direction: string|null, status: string|null, config: array<string, mixed>}
     * }>
     */
    protected function resolveFanoutTargets(Thread $thread): array
    {
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

        $channelTargets = $thread->channels()
            ->wherePivot('status', 'active')
            ->get()
            ->map(function (Channel $channel): ?array {
                $bindingDirection = $this->normalizedDirection(data_get($channel->pivot, 'direction'));

                if (! in_array($bindingDirection, [Channel::DirectionOutbound, Channel::DirectionBidirectional], true)) {
                    return null;
                }

                $bindingData = $this->arrayValue(data_get($channel->pivot, 'data'));
                $providerIdentifier = $this->normalizedTarget(data_get($bindingData, 'provider_identifier'));
                $config = $this->arrayValue(data_get($bindingData, 'config'));

                return [
                    'protocol' => ChannelProtocol::Key,
                    'provider' => $this->normalizedProvider($channel->driver) ?? Channel::DriverGeneric,
                    'target' => $providerIdentifier ?? $channel->uuid,
                    'route_key' => 'channel:'.$channel->uuid.':'.($providerIdentifier ?? 'none'),
                    'channel' => [
                        'uuid' => $channel->uuid,
                        'id' => $channel->id,
                        'driver' => $channel->driver,
                    ],
                    'binding' => [
                        'provider_identifier' => $providerIdentifier,
                        'direction' => $bindingDirection,
                        'status' => data_get($channel->pivot, 'status'),
                        'config' => $config,
                        'kind' => data_get($channel->pivot, 'kind', ChannelRelation::KindBind),
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
