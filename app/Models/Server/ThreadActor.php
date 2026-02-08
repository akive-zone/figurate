<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThreadActor extends Model
{
    /** @use HasFactory<\Database\Factories\ThreadActorFactory> */
    use HasFactory;

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
        'thread_id',
        'actor_key',
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

    public function memories(): HasMany
    {
        return $this->hasMany(ThreadActorMemory::class);
    }
}
