<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AgentTask extends Model
{
    /** @use HasFactory<\Database\Factories\AgentTaskFactory> */
    use HasFactory, HasPublicUuid;

    use \Staudenmeir\EloquentJsonRelations\HasJsonRelationships;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'thread_id',
        'message_id',
        'user_id',
        'remote',
        'status',
        'last_payload',
        'completed_at',
        'failed_at',
        'canceled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'remote' => 'array',
            'last_payload' => 'array',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'canceled_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function threadEvents(): BelongsToMany
    {
        return $this->belongsToMany(ThreadEvent::class, 'thread_event_agent_tasks')
            ->withTimestamps();
    }
}
