<?php

namespace App\Support\Channels;

use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use Illuminate\Database\Eloquent\Model;

class ChannelRegistry
{
    public function __construct(
        protected ChannelDriverRegistry $channelDriverRegistry,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function register(Model $owner, array $attributes): Channel
    {
        $this->ensureOwnerSupportsChannels($owner);

        $driverKey = strtolower(trim((string) ($attributes['driver'] ?? '')));
        $name = trim((string) ($attributes['name'] ?? ''));
        $driver = $this->channelDriverRegistry->resolveByDriver($driverKey);
        $prepared = $driver->prepareForRegistration($attributes);
        $kind = $this->kindValue($prepared['kind'] ?? null);

        $channel = $this->existingOwnedChannel($owner, $driverKey, $name, $kind)
            ?? Channel::query()->make();

        $channel->forceFill($this->channelAttributes($prepared, $driverKey, $name))->save();

        $owner->channelRelations()->updateOrCreate(
            [
                'channel_id' => $channel->id,
                'kind' => $kind,
            ],
            $this->relationAttributes($prepared),
        );

        return $channel->fresh() ?? $channel;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $owner, Channel $channel, array $attributes): Channel
    {
        $this->ensureOwnerSupportsChannels($owner);

        $driver = $this->channelDriverRegistry->resolveByChannel($channel);
        $prepared = $driver->prepareForUpdate($channel, $attributes);
        $channel->fill($this->updatableChannelAttributes($prepared))->save();

        $relationUpdates = collect([
            'status' => $prepared['status'] ?? null,
            'direction' => $prepared['direction'] ?? null,
            'data' => $prepared['data'] ?? null,
            'meta' => $prepared['meta'] ?? null,
        ])->filter(fn (mixed $value): bool => $value !== null)->all();

        if ($relationUpdates !== []) {
            $owner->channelRelations()
                ->where('channel_id', $channel->id)
                ->when(isset($prepared['kind']), fn ($query) => $query->where('kind', $this->kindValue($prepared['kind'])))
                ->update($relationUpdates);
        }

        return $channel->fresh() ?? $channel;
    }

    protected function ensureOwnerSupportsChannels(Model $owner): void
    {
        if (! method_exists($owner, 'channelRelations')) {
            throw new \RuntimeException('Channel owner does not support channel relations.');
        }
    }

    protected function existingOwnedChannel(Model $owner, string $driver, string $name, string $kind): ?Channel
    {
        return Channel::query()
            ->where('driver', $driver)
            ->where('server', $name)
            ->whereHas('relations', function ($query) use ($owner, $kind): void {
                $query
                    ->where('relationable_type', $owner->getMorphClass())
                    ->where('relationable_id', $owner->getKey())
                    ->where('kind', $kind);
            })
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function channelAttributes(array $attributes, string $driver, string $name): array
    {
        return [
            'name' => (string) ($attributes['label'] ?? $name),
            'driver' => $driver,
            'server' => $name,
            'label' => $attributes['label'] ?? null,
            'enabled' => (bool) ($attributes['enabled'] ?? true),
            'priority' => (int) ($attributes['priority'] ?? 0),
            'transport' => $attributes['transport'] ?? 'remote',
            'status' => $attributes['status'] ?? Channel::StatusActive,
            'direction' => $attributes['direction'] ?? Channel::DirectionBidirectional,
            'endpoint_url' => $attributes['endpoint_url'] ?? null,
            'handler' => $attributes['handler'] ?? null,
            'allowed_tools' => is_array($attributes['allowed_tools'] ?? null) ? array_values($attributes['allowed_tools']) : [],
            'auth_type' => $attributes['auth_type'] ?? null,
            'credentials' => is_array($attributes['credentials'] ?? null) ? $attributes['credentials'] : [],
            'config' => is_array($attributes['config'] ?? null) ? $attributes['config'] : [],
            'meta' => is_array($attributes['meta'] ?? null) ? $attributes['meta'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function updatableChannelAttributes(array $attributes): array
    {
        return collect([
            'name' => array_key_exists('name', $attributes) ? (string) $attributes['name'] : null,
            'server' => array_key_exists('name', $attributes) ? (string) $attributes['name'] : null,
            'label' => array_key_exists('label', $attributes) ? $attributes['label'] : null,
            'enabled' => array_key_exists('enabled', $attributes) ? $attributes['enabled'] : null,
            'priority' => array_key_exists('priority', $attributes) ? $attributes['priority'] : null,
            'transport' => array_key_exists('transport', $attributes) ? $attributes['transport'] : null,
            'status' => array_key_exists('status', $attributes) ? $attributes['status'] : null,
            'direction' => array_key_exists('direction', $attributes) ? $attributes['direction'] : null,
            'endpoint_url' => array_key_exists('endpoint_url', $attributes) ? $attributes['endpoint_url'] : null,
            'handler' => array_key_exists('handler', $attributes) ? $attributes['handler'] : null,
            'allowed_tools' => array_key_exists('allowed_tools', $attributes)
                ? (is_array($attributes['allowed_tools']) ? array_values($attributes['allowed_tools']) : [])
                : null,
            'auth_type' => array_key_exists('auth_type', $attributes) ? $attributes['auth_type'] : null,
            'credentials' => array_key_exists('credentials', $attributes) && is_array($attributes['credentials'])
                ? $attributes['credentials']
                : (array_key_exists('credentials', $attributes) ? [] : null),
            'config' => array_key_exists('config', $attributes) && is_array($attributes['config'])
                ? $attributes['config']
                : (array_key_exists('config', $attributes) ? [] : null),
            'meta' => array_key_exists('meta', $attributes) && is_array($attributes['meta'])
                ? $attributes['meta']
                : (array_key_exists('meta', $attributes) ? [] : null),
        ])->filter(fn (mixed $value): bool => $value !== null)->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function relationAttributes(array $attributes): array
    {
        return [
            'status' => $attributes['status'] ?? Channel::StatusActive,
            'direction' => $attributes['direction'] ?? Channel::DirectionBidirectional,
            'data' => is_array($attributes['data'] ?? null) ? $attributes['data'] : [],
            'meta' => is_array($attributes['meta'] ?? null) ? $attributes['meta'] : [],
        ];
    }

    protected function kindValue(mixed $value): string
    {
        $kind = strtolower(trim((string) ($value ?? ChannelRelation::KindLink)));

        return $kind !== '' ? $kind : ChannelRelation::KindLink;
    }
}
