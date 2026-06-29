<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Channel extends Model implements HasMedia
{
    /** @use HasFactory<ChannelFactory> */
    use HasFactory, HasPublicUuid, InteractsWithMedia, SoftDeletes;

    public const StatusActive = 'active';

    public const StatusPaused = 'paused';

    public const StatusDisabled = 'disabled';

    public const DirectionInbound = 'inbound';

    public const DirectionOutbound = 'outbound';

    public const DirectionBidirectional = 'bidirectional';

    public const ProtocolGeneric = 'generic';

    public const ProtocolMcp = 'mcp';

    public const ProtocolA2a = 'a2a';

    public const ProtocolAcp = 'acp';

    public const TransportHttp = 'http';

    public const TransportWebhook = 'webhook';

    public const TransportWebsocket = 'websocket';

    public const TransportWebrtc = 'webrtc';

    public const TransportRelay = 'relay';

    public const TransportStdio = 'stdio';

    public const SkillCollection = 'skills';

    public const HealthHealthy = 'healthy';

    public const HealthUnhealthy = 'unhealthy';

    public const HealthUnknown = 'unknown';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'driver',
        'server',
        'label',
        'enabled',
        'priority',
        'transport',
        'status',
        'direction',
        'endpoint_url',
        'handler',
        'allowed_tools',
        'auth_type',
        'credentials',
        'config',
        'meta',
        'last_connected_at',
        'last_message_at',
        'health_status',
        'metrics',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'priority' => 'integer',
            'transport' => 'string',
            'allowed_tools' => 'array',
            'credentials' => 'encrypted:array',
            'config' => 'array',
            'meta' => 'array',
            'last_connected_at' => 'datetime',
            'last_message_at' => 'datetime',
            'metrics' => 'array',
        ];
    }

    public function relations(): HasMany
    {
        return $this->hasMany(ChannelRelation::class, 'channel_id');
    }

    public function connections(): HasMany
    {
        return $this->relations();
    }

    public function routes(): HasMany
    {
        return $this->hasMany(ChannelRoute::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::SkillCollection);
    }

    public function threads(): MorphToMany
    {
        return $this->morphedByMany(Thread::class, 'relationable', 'channel_relations', 'channel_id', 'relationable_id')
            ->wherePivot('kind', ChannelRelation::KindBind)
            ->withPivot([
                'id',
                'kind',
                'status',
                'direction',
                'config',
                'data',
                'meta',
            ])
            ->withTimestamps();
    }

    public function protocolKey(): string
    {
        $stored = is_string($this->driver) ? strtolower(trim($this->driver)) : '';

        return match ($stored) {
            self::ProtocolMcp,
            self::ProtocolA2a,
            self::ProtocolAcp,
            self::ProtocolGeneric => $stored,
            default => self::ProtocolGeneric,
        };
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     */
    public function transportKey(array $connectionConfig = []): ?string
    {
        $configuredTransport = data_get($connectionConfig, 'transport');

        if (is_string($configuredTransport) && trim($configuredTransport) !== '') {
            $normalizedTransport = strtolower(trim($configuredTransport));

            if (! in_array($normalizedTransport, ['remote', 'local'], true)) {
                return $normalizedTransport;
            }
        }

        if (is_string($this->transport) && trim($this->transport) !== '') {
            $normalizedTransport = strtolower(trim($this->transport));

            if (! in_array($normalizedTransport, ['remote', 'local'], true)) {
                return $normalizedTransport;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function supportedProtocols(): array
    {
        return [
            self::ProtocolGeneric,
            self::ProtocolMcp,
            self::ProtocolA2a,
            self::ProtocolAcp,
        ];
    }

    /**
     * @return list<string>
     */
    public static function supportedTransports(): array
    {
        return [
            self::TransportHttp,
            self::TransportWebhook,
            self::TransportWebsocket,
            self::TransportWebrtc,
            self::TransportRelay,
            self::TransportStdio,
        ];
    }

    /**
     * Mark channel as connected
     */
    public function markConnected(): void
    {
        $this->update([
            'last_connected_at' => now(),
            'health_status' => self::HealthHealthy,
        ]);
    }

    /**
     * Mark channel as disconnected
     */
    public function markDisconnected(): void
    {
        $this->update([
            'health_status' => self::HealthUnhealthy,
        ]);
    }

    /**
     * Record message activity
     */
    public function recordMessageActivity(): void
    {
        $this->update([
            'last_message_at' => now(),
            'health_status' => self::HealthHealthy,
        ]);

        $this->incrementMetric('message_count');
    }

    /**
     * Record error
     */
    public function recordError(): void
    {
        $this->incrementMetric('error_count');

        // Mark as unhealthy if too many errors
        $metrics = is_array($this->metrics) ? $this->metrics : [];
        $errorCount = $metrics['error_count'] ?? 0;

        if ($errorCount >= 5) {
            $this->update(['health_status' => self::HealthUnhealthy]);
        }
    }

    /**
     * Increment a metric counter
     */
    protected function incrementMetric(string $key, int $amount = 1): void
    {
        $metrics = is_array($this->metrics) ? $this->metrics : [];
        $metrics[$key] = ($metrics[$key] ?? 0) + $amount;

        $this->update(['metrics' => $metrics]);
    }

    /**
     * Check if channel is healthy
     */
    public function isHealthy(): bool
    {
        return $this->health_status === self::HealthHealthy;
    }

    /**
     * Check if channel is unhealthy
     */
    public function isUnhealthy(): bool
    {
        return $this->health_status === self::HealthUnhealthy;
    }
}
