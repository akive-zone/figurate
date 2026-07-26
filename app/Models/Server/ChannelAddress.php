<?php

namespace App\Models\Server;

use Database\Factories\Server\ChannelAddressFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ChannelAddress extends Model implements HasMedia
{
    /** @use HasFactory<ChannelAddressFactory> */
    use HasFactory, HasUlids, InteractsWithMedia;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'channel_route_id',
        'addressable_type',
        'addressable_id',
        'label',
        'provider',
        'target',
        'target_type',
        'status',
        'direction',
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
            'data' => 'array',
            'meta' => 'array',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(ChannelRoute::class, 'channel_route_id');
    }

    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(Channel::SkillCollection);
    }
}
