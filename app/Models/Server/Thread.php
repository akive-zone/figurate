<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Thread extends Model
{
    /** @use HasFactory<\Database\Factories\ThreadFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    public const PurposeMain = 'main';

    public const PurposePlanning = 'planning';

    public const PurposeExecution = 'execution';

    public const PurposeBilling = 'billing';

    public const PurposeDispute = 'dispute';

    public const PurposeSupport = 'support';

    public const PurposeSystem = 'system';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'threadable_type',
        'threadable_id',
        'purpose',
        'title',
        'phase',
        'status',
    ];

    public function threadable(): MorphTo
    {
        return $this->morphTo();
    }

    public function messages(): MorphMany
    {
        return $this->morphMany(Message::class, 'messageable');
    }

    public function posts(): MorphMany
    {
        return $this->morphMany(Post::class, 'postable');
    }

    public function actors(): HasMany
    {
        return $this->hasMany(ThreadActor::class);
    }

    public function actorSessions(): HasMany
    {
        return $this->hasMany(ThreadActorSession::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ThreadEvent::class);
    }

    public function relations(): HasMany
    {
        return $this->hasMany(ThreadRelation::class);
    }

    public function stores(): MorphToMany
    {
        return $this->morphToMany(Store::class, 'storeable', 'storeables', 'storeable_id', 'store_id')
            ->withPivot(['scope', 'created_by', 'meta'])
            ->withTimestamps();
    }

    public function contextServers(): MorphMany
    {
        return $this->morphMany(ContextServer::class, 'contextable');
    }
}
