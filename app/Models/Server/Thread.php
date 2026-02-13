<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
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
    use HasFactory, HasPublicUuid, SoftDeletes;

    public const AgentRequest = 'request_agent';

    public const AgentOrder = 'order_agent';

    public const AgentHumanChat = 'human_chat';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'threadable_type',
        'threadable_id',
        'created_by',
        'title',
        'phase',
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

    public function actors(): HasMany
    {
        return $this->hasMany(ThreadActor::class);
    }

    public function actorMemories(): HasMany
    {
        return $this->hasMany(ThreadActorMemory::class);
    }

    public function primaryHandlerActor(): HasMany
    {
        return $this->actors()
            ->where('role', ThreadActor::RolePrimaryHandler)
            ->where('status', ThreadActor::StatusActive)
            ->orderBy('priority');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ThreadEvent::class);
    }
}
