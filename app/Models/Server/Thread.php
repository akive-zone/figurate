<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Thread extends Model
{
    /** @use HasFactory<\Database\Factories\ThreadFactory> */
    use HasFactory, SoftDeletes;

    public const AgentRequest = 'request_agent';

    public const AgentOrder = 'order_agent';

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
}
