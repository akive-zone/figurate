<?php

namespace App\Models\Server;

use ApiPlatform\Laravel\Eloquent\Filter\EqualsFilter;
use ApiPlatform\Laravel\Eloquent\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ApiResource(
    routePrefix: '/studio',
    operations: [
        new GetCollection(security: "is_granted('viewAny')"),
        new Get(security: "is_granted('view', object)"),
        new Post(security: "is_granted('create')"),
    ],
)]
#[QueryParameter(key: 'order_id', filter: EqualsFilter::class, property: 'order_id')]
#[QueryParameter(key: 'profile_id', filter: EqualsFilter::class, property: 'profile_id')]
#[QueryParameter(key: 'type', filter: EqualsFilter::class, property: 'type')]
#[QueryParameter(key: 'order', filter: OrderFilter::class, properties: ['created_at' => 'created_at'])]
class Process extends Model
{
    /** @use HasFactory<\Database\Factories\ProcessFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'profile_id',
        'type',
        'content',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
