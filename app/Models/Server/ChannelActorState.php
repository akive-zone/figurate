<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ChannelActorState extends Model
{
    /** @use HasFactory<\Database\Factories\ChannelActorStateFactory> */
    use HasFactory;

    public const StatusActive = 'active';

    public const StatusPaused = 'paused';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'channel_id',
        'thread_id',
        'actor_type',
        'actor_id',
        'status',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function activeThread(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'thread_id');
    }
}
