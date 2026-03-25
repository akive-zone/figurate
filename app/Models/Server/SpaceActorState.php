<?php

namespace App\Models\Server;

use Database\Factories\SpaceActorStateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SpaceActorState extends Model
{
    /** @use HasFactory<SpaceActorStateFactory> */
    use HasFactory;

    protected $table = 'actor_states';

    public const StatusActive = 'active';

    public const StatusPaused = 'paused';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'space_id',
        'thread_id',
        'actorable_type',
        'actorable_id',
        'status',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function actor(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'actorable_type', 'actorable_id');
    }

    public function activeThread(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'thread_id');
    }
}
