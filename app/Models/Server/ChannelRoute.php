<?php

namespace App\Models\Server;

use Database\Factories\Server\ChannelRouteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ChannelRoute extends Model implements HasMedia
{
    /** @use HasFactory<ChannelRouteFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * @var list<string>
     */
    protected $fillable = [
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(Channel::SkillCollection);
    }
}
