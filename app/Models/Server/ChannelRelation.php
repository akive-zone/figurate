<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ChannelRelation extends Model
{
    use HasUlids;

    public const KindLink = 'link';

    public const KindBind = 'bind';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'channel_id',
        'relationable_type',
        'relationable_id',
        'kind',
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
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    public function relationable(): MorphTo
    {
        return $this->morphTo();
    }
}
