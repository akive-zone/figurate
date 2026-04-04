<?php

namespace App\Support\Channels;

use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use Illuminate\Database\Eloquent\Model;

class ChannelConnectionRegistry
{
    public function __construct(
        protected ChannelDriverRegistry $channelDriverRegistry,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Model $owner, Channel $channel, array $attributes): ChannelRelation
    {
        $this->ensureOwnerSupportsChannels($owner);

        $connection = $owner->channelRelations()->create(
            $this->connectionAttributes($channel, $attributes),
        );

        return $connection->fresh(['channel']) ?? $connection;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model $owner, Channel $channel, ChannelRelation $connection, array $attributes): ChannelRelation
    {
        $this->ensureOwnerSupportsChannels($owner);

        if ((int) $connection->channel_id !== (int) $channel->id) {
            throw new \RuntimeException('Channel connection does not belong to the selected channel.');
        }

        if (
            $connection->relationable_type !== $owner->getMorphClass() ||
            (int) $connection->relationable_id !== (int) $owner->getKey()
        ) {
            throw new \RuntimeException('Channel connection does not belong to the selected owner.');
        }

        $connection->fill($this->updatableConnectionAttributes($channel, $attributes))->save();

        return $connection->fresh(['channel']) ?? $connection;
    }

    protected function ensureOwnerSupportsChannels(Model $owner): void
    {
        if (! method_exists($owner, 'channelRelations')) {
            throw new \RuntimeException('Channel owner does not support channel relations.');
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function connectionAttributes(Channel $channel, array $attributes): array
    {
        $config = $this->connectionConfig($channel, $attributes);

        return [
            'channel_id' => $channel->id,
            'kind' => $this->kindValue($attributes['kind'] ?? null),
            'status' => $this->statusValue($attributes['status'] ?? null),
            'direction' => $this->directionValue($attributes['direction'] ?? null),
            'config' => $config !== [] ? $config : [],
            'data' => is_array($attributes['data'] ?? null) ? $attributes['data'] : [],
            'meta' => is_array($attributes['meta'] ?? null) ? $attributes['meta'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function updatableConnectionAttributes(Channel $channel, array $attributes): array
    {
        $config = array_key_exists('config', $attributes)
            || array_key_exists('protocol', $attributes)
            || array_key_exists('transport', $attributes)
            || array_key_exists('mode', $attributes)
            || array_key_exists('endpoint_url', $attributes)
            || array_key_exists('handler', $attributes)
            || array_key_exists('auth_type', $attributes)
            || array_key_exists('credentials', $attributes)
            ? $this->connectionConfig($channel, $attributes)
            : null;

        return collect([
            'kind' => array_key_exists('kind', $attributes) ? $this->kindValue($attributes['kind']) : null,
            'status' => array_key_exists('status', $attributes) ? $this->statusValue($attributes['status']) : null,
            'direction' => array_key_exists('direction', $attributes) ? $this->directionValue($attributes['direction']) : null,
            'config' => $config,
            'data' => array_key_exists('data', $attributes) && is_array($attributes['data'])
                ? $attributes['data']
                : (array_key_exists('data', $attributes) ? [] : null),
            'meta' => array_key_exists('meta', $attributes) && is_array($attributes['meta'])
                ? $attributes['meta']
                : (array_key_exists('meta', $attributes) ? [] : null),
        ])->filter(fn (mixed $value): bool => $value !== null)->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function connectionConfig(Channel $channel, array $attributes): array
    {
        $config = is_array($attributes['config'] ?? null) ? $attributes['config'] : [];

        $protocol = array_key_exists('protocol', $attributes)
            ? $this->protocolValue($channel, $attributes['protocol'])
            : $this->protocolValue($channel, $config['protocol'] ?? null);
        if ($protocol !== null) {
            $config['protocol'] = $protocol;
        }

        $transport = array_key_exists('transport', $attributes)
            ? $this->transportValue($channel, $attributes['transport'])
            : $this->transportValue($channel, $config['transport'] ?? null);
        if ($transport !== null) {
            $config['transport'] = $transport;
        }

        $mode = array_key_exists('mode', $attributes)
            ? $this->modeValue($attributes['mode'])
            : $this->modeValue($config['mode'] ?? null);
        if ($mode !== null) {
            $config['mode'] = $mode;
        }

        $endpointUrl = array_key_exists('endpoint_url', $attributes)
            ? $this->stringValue($attributes['endpoint_url'])
            : $this->stringValue($config['endpoint_url'] ?? null);
        if ($endpointUrl !== null) {
            $config['endpoint_url'] = $endpointUrl;
        }

        $handler = array_key_exists('handler', $attributes)
            ? $this->stringValue($attributes['handler'])
            : $this->stringValue($config['handler'] ?? null);
        if ($handler !== null) {
            $config['handler'] = $handler;
        }

        $authType = array_key_exists('auth_type', $attributes)
            ? $this->stringValue($attributes['auth_type'])
            : $this->stringValue($config['auth_type'] ?? null);
        if ($authType !== null) {
            $config['auth_type'] = $authType;
        }

        if (array_key_exists('credentials', $attributes)) {
            $config['credentials'] = is_array($attributes['credentials']) ? $attributes['credentials'] : [];
        } elseif (isset($config['credentials']) && ! is_array($config['credentials'])) {
            unset($config['credentials']);
        }

        return $config;
    }

    protected function kindValue(mixed $value): string
    {
        $kind = strtolower(trim((string) ($value ?? ChannelRelation::KindLink)));

        return $kind !== '' ? $kind : ChannelRelation::KindLink;
    }

    protected function statusValue(mixed $value): string
    {
        $status = strtolower(trim((string) ($value ?? Channel::StatusActive)));

        return $status !== '' ? $status : Channel::StatusActive;
    }

    protected function directionValue(mixed $value): string
    {
        $direction = strtolower(trim((string) ($value ?? Channel::DirectionBidirectional)));

        return $direction !== '' ? $direction : Channel::DirectionBidirectional;
    }

    protected function protocolValue(Channel $channel, mixed $value): ?string
    {
        $protocol = $this->stringValue($value);

        if ($protocol === null) {
            return null;
        }

        $driver = $this->channelDriverRegistry->resolveByChannel($channel);
        $supportedProtocols = collect($driver->supportedProtocols())
            ->filter(fn (mixed $supportedProtocol): bool => is_string($supportedProtocol) && trim($supportedProtocol) !== '')
            ->map(fn (string $supportedProtocol): string => strtolower(trim($supportedProtocol)))
            ->values()
            ->all();

        $normalized = strtolower($protocol);

        if ($supportedProtocols !== [] && ! in_array($normalized, $supportedProtocols, true)) {
            throw new \InvalidArgumentException("Unsupported protocol [{$normalized}] for channel system [{$channel->driver}].");
        }

        return $normalized;
    }

    protected function transportValue(Channel $channel, mixed $value): ?string
    {
        $transport = $this->stringValue($value);

        if ($transport === null) {
            return null;
        }

        $driver = $this->channelDriverRegistry->resolveByChannel($channel);
        $supportedTransports = collect($driver->supportedTransports())
            ->filter(fn (mixed $supportedTransport): bool => is_string($supportedTransport) && trim($supportedTransport) !== '')
            ->map(fn (string $supportedTransport): string => strtolower(trim($supportedTransport)))
            ->values()
            ->all();

        $normalized = strtolower($transport);

        if ($supportedTransports !== [] && ! in_array($normalized, $supportedTransports, true)) {
            throw new \InvalidArgumentException("Unsupported transport [{$normalized}] for channel system [{$channel->driver}].");
        }

        return $normalized;
    }

    protected function modeValue(mixed $value): ?string
    {
        $mode = $this->stringValue($value);

        return $mode !== null ? strtolower($mode) : null;
    }

    protected function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
