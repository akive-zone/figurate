<?php

namespace App\Models\Server;

use Database\Factories\Server\ChannelRouteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelRoute extends Model
{
    /** @use HasFactory<ChannelRouteFactory> */
    use HasFactory, HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'channel_id',
        'name',
        'label',
        'status',
        'direction',
        'config',
        'data',
        'meta',
    ];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'data' => 'array',
            'meta' => 'array',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ChannelAddress::class);
    }
}
