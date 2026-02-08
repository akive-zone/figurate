<?php

namespace App\Models\Server;

use ApiPlatform\Laravel\Eloquent\Filter\EqualsFilter;
use ApiPlatform\Laravel\Eloquent\Filter\OrderFilter;
use ApiPlatform\Laravel\Eloquent\Filter\PartialSearchFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ApiResource(
    routePrefix: '/studio',
    operations: [
        new GetCollection(security: "is_granted('viewAny')"),
        new Get(security: "is_granted('view', object)"),
        new Post(security: "is_granted('create')"),
        new Patch(security: "is_granted('update', object)"),
    ],
)]
#[QueryParameter(key: 'status', filter: EqualsFilter::class, property: 'status')]
#[QueryParameter(key: 'flow_type', filter: EqualsFilter::class, property: 'flow_type')]
#[QueryParameter(key: 'title', filter: PartialSearchFilter::class, property: 'title')]
#[QueryParameter(key: 'order', filter: OrderFilter::class, properties: ['created_at' => 'created_at'])]
class Request extends Model
{
    /** @use HasFactory<\Database\Factories\RequestFactory> */
    use HasFactory, SoftDeletes;

    public const ActionAsker = 'asker';

    public const ActionTargetProfile = 'target_profile';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'flow_type',
        'title',
        'description',
        'status',
    ];

    public function users(): MorphToMany
    {
        return $this->morphedByMany(User::class, 'actor', 'request_actors')
            ->withPivot(['action', 'status'])
            ->withTimestamps();
    }

    public function profiles(): MorphToMany
    {
        return $this->morphedByMany(Profile::class, 'actor', 'request_actors')
            ->withPivot(['action', 'status'])
            ->withTimestamps();
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function channels(): MorphToMany
    {
        return $this->morphToMany(
            Channel::class,
            'relationable',
            'channel_relations',
            'relationable_id',
            'channel_id'
        )->withTimestamps();
    }

    public function messages(): MorphMany
    {
        return $this->morphMany(Message::class, 'messageable');
    }

    public function latestMessage(): MorphOne
    {
        return $this->morphOne(Message::class, 'messageable')->latestOfMany();
    }

    public function threads(): MorphMany
    {
        return $this->morphMany(Thread::class, 'threadable');
    }

    public function hasUserActor(User $user, ?string $action = null): bool
    {
        $query = $this->users()->whereKey($user->id);

        if ($action !== null) {
            $query->wherePivot('action', $action);
        }

        return $query->exists();
    }

    public function hasProfileActorForUser(User $user, ?string $action = null): bool
    {
        $query = $this->profiles()->where('profiles.user_id', $user->id);

        if ($action !== null) {
            $query->wherePivot('action', $action);
        }

        return $query->exists();
    }

    public function hasParticipant(User $user): bool
    {
        return $this->hasUserActor($user) || $this->hasProfileActorForUser($user);
    }

    public function primaryRequester(): ?User
    {
        return $this->users()
            ->wherePivot('action', self::ActionAsker)
            ->first();
    }
}
