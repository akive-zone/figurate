<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\ThreadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Thread extends Model
{
    /** @use HasFactory<ThreadFactory> */
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

    public function posts(): MorphMany
    {
        return $this->morphMany(Post::class, 'postable');
    }

    public function actors(): HasMany
    {
        return $this->hasMany(ThreadActor::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ThreadEvent::class);
    }

    public function outboxes(): HasMany
    {
        return $this->hasMany(Outbox::class);
    }

    public function inboxes(): MorphMany
    {
        return $this->morphMany(Inbox::class, 'inboxable');
    }

    public function contextInboxes(): HasMany
    {
        return $this->hasMany(Inbox::class);
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

    public function channelRelations(): MorphMany
    {
        return $this->morphMany(ChannelRelation::class, 'relationable');
    }

    public function contextServers(): MorphToMany
    {
        return $this->morphToMany(Channel::class, 'relationable', 'channel_relations', 'relationable_id', 'channel_id')
            ->wherePivot('kind', ChannelRelation::KindLink)
            ->where('driver', Channel::DriverMcp)
            ->withPivot([
                'kind',
                'status',
                'direction',
                'data',
                'meta',
            ])
            ->withTimestamps();
    }

    public function channels(): MorphToMany
    {
        return $this->morphToMany(Channel::class, 'relationable', 'channel_relations', 'relationable_id', 'channel_id')
            ->wherePivot('kind', ChannelRelation::KindBind)
            ->withPivot([
                'kind',
                'status',
                'direction',
                'data',
                'meta',
            ])
            ->withTimestamps();
    }
}
