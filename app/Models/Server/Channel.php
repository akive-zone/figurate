<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Channel extends Model
{
    /** @use HasFactory<\Database\Factories\ChannelFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'status',
    ];

    public function actorStates(): HasMany
    {
        return $this->hasMany(ChannelActorState::class);
    }

    public function relations(): HasMany
    {
        return $this->hasMany(ChannelRelation::class);
    }

    public function requests(): MorphToMany
    {
        return $this->morphedByMany(
            Request::class,
            'relationable',
            'channel_relations',
            'channel_id',
            'relationable_id'
        )->withTimestamps();
    }

    public function posts(): MorphMany
    {
        return $this->morphMany(Post::class, 'postable');
    }
}
