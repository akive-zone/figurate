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

    public const DriverGeneric = 'generic';

    public const DriverMcp = 'mcp';

    public const DriverStdio = 'stdio';

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
}
