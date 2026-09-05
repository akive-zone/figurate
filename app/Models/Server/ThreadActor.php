<?php

namespace App\Models\Server;

use Database\Factories\ThreadActorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ThreadActor extends Model
{
    /** @use HasFactory<ThreadActorFactory> */
    use HasFactory;

    public const RoleListener = 'listener';

    public const RoleMember = 'member';

    public const StatusActive = 'active';

    public const StatusPaused = 'paused';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'thread_id',
        'actorable_type',
        'actorable_id',
        'role',
        'status',
        'priority',
        'config',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'config' => 'array',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function actorable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actorName(): ?string
    {
        if (! is_string($this->actorable_type) || $this->actorable_type === '') {
            return null;
        }

        return $this->actorable_type;
    }

    public function isNamedActor(string $actorName): bool
    {
        return $this->actorable_id === null && $this->actorName() === $actorName;
    }

    public function actorReference(): ?string
    {
        $actorName = $this->actorName();

        if (! $actorName) {
            return null;
        }

        return $this->actorable_id === null ? $actorName : "{$actorName}:{$this->actorable_id}";
    }
}
