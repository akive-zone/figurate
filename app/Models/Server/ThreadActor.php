<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ThreadActor extends Model
{
    /** @use HasFactory<\Database\Factories\ThreadActorFactory> */
    use HasFactory, HasPublicUuid;

    public const RolePrimaryHandler = 'primary_handler';

    public const RoleObserver = 'observer';

    public const RoleParticipant = 'participant';

    public const StatusActive = 'active';

    public const StatusPaused = 'paused';

    public const ActorRequestAgent = 'request_agent';

    public const ActorOrderAgent = 'order_agent';

    public const ActorHumanChat = 'human_chat';

    public const ActorSafetyGuard = 'safety_guard';

    public const ActorAssistantSuggester = 'assistant_suggester';

    public const ModePassive = 'passive';

    public const ModeEnforcing = 'enforcing';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
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

    public function memories(): HasMany
    {
        return $this->hasMany(ThreadActorMemory::class);
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
