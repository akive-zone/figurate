<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreadActorMemory extends Model
{
    /** @use HasFactory<\Database\Factories\ThreadActorMemoryFactory> */
    use HasFactory, HasPublicUuid;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'thread_id',
        'thread_actor_id',
        'provider',
        'model',
        'conversation_id',
        'state',
        'last_used_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function threadActor(): BelongsTo
    {
        return $this->belongsTo(ThreadActor::class);
    }
}
