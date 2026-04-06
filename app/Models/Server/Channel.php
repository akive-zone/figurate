<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Channel extends Model
{
    /** @use HasFactory<ChannelFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

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

    public const DriverGeneric = 'generic';

    public const DriverMcp = 'mcp';

    public const DriverA2a = 'a2a';

    public const DriverAcp = 'acp';

    public const DriverStdio = self::TransportStdio;

    public const SystemGeneric = self::DriverGeneric;

    public const SystemMcp = self::DriverMcp;

    public const SystemA2a = self::DriverA2a;

    public const SystemAcp = self::DriverAcp;

    public const SystemStdio = self::DriverStdio;

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
}
