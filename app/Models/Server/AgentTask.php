<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\AgentTaskFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;

class AgentTask extends Model
{
    /** @use HasFactory<AgentTaskFactory> */
    use HasFactory, HasPublicUuid;

    use HasJsonRelationships;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'thread_id',
        'post_id',
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
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function messageId(): Attribute
    {
        return Attribute::make(
            get: fn (): ?int => $this->post_id,
            set: fn (mixed $value): array => ['post_id' => $value],
        );
    }

    public function threadEvents(): BelongsToMany
    {
        return $this->belongsToMany(ThreadEvent::class, 'thread_event_agent_tasks')
            ->withTimestamps();
    }
}
