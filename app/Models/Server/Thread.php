<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Thread extends Model
{
    /** @use HasFactory<\Database\Factories\ThreadFactory> */
    use HasFactory, SoftDeletes;

    public const AgentRequest = 'request_agent';

    public const AgentOrder = 'order_agent';

    public const AgentHumanChat = 'human_chat';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'threadable_type',
        'threadable_id',
        'created_by',
        'title',
        'phase',
        'agent_key',
        'ai_conversation_id',
        'status',
    ];

    public function threadable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): MorphMany
    {
        return $this->morphMany(Message::class, 'messageable');
    }

    public function observers(): HasMany
    {
        return $this->hasMany(ThreadObserver::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ThreadEvent::class);
    }
}
