<?php

namespace App\Models\Server;

use ApiPlatform\Laravel\Eloquent\Filter\DateFilter;
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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
#[QueryParameter(key: 'user_id', filter: EqualsFilter::class, property: 'user_id')]
#[QueryParameter(key: 'status', filter: EqualsFilter::class, property: 'status')]
#[QueryParameter(key: 'display_name', filter: PartialSearchFilter::class, property: 'display_name')]
#[QueryParameter(key: 'location', filter: PartialSearchFilter::class, property: 'location')]
#[QueryParameter(key: 'approved_at', filter: DateFilter::class, property: 'approved_at')]
#[QueryParameter(key: 'order', filter: OrderFilter::class, properties: ['created_at' => 'created_at'])]
class Profile extends Model
{
    /** @use HasFactory<\Database\Factories\ProfileFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'display_name',
        'bio',
        'location',
        'status',
        'approved_at',
        'approved_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ServiceCategory::class, 'profile_service_category');
    }

    public function requests(): MorphToMany
    {
        return $this->morphToMany(Request::class, 'actor', 'request_actors')
            ->withPivot(['action', 'status'])
            ->withTimestamps();
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'seller_profile_id');
    }

    public function workLogs(): HasMany
    {
        return $this->hasMany(Process::class);
    }
}
