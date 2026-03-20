<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ThreadEvent extends Model
{
    /** @use HasFactory<\Database\Factories\ThreadEventFactory> */
    use HasFactory, HasPublicUuid;

    public const LayerExecution = 'execution';

    public const KindAcp = 'acp';

    public const KindA2a = 'a2a';

    public const KindMcp = 'mcp';

    public const KindOrchestration = 'orchestration';

    public const KindObserver = 'observer';

    public const StateRequested = 'requested';

    public const StateReceived = 'received';

    public const StateCompleted = 'completed';

    public const StateFailed = 'failed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'thread_id',
        'thread_actor_id',
        'message_id',
        'event_key',
        'layer',
        'kind',
        'operation',
        'state',
        'event_type',
        'severity',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function threadActor(): BelongsTo
    {
        return $this->belongsTo(ThreadActor::class);
    }

    public function agentTasks(): BelongsToMany
    {
        return $this->belongsToMany(AgentTask::class, 'thread_event_agent_tasks')
            ->withTimestamps();
    }

    public function tasks(): BelongsToMany
    {
        return $this->agentTasks();
    }

    public function inboxes(): MorphMany
    {
        return $this->morphMany(Inbox::class, 'inboxable');
    }
}
