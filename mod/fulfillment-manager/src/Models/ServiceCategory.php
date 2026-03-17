<?php

namespace Figurate\FulfillmentManager\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Server\Profile;
use Figurate\FulfillmentManager\Database\Factories\ServiceCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(ServiceCategoryFactory::class)]
class ServiceCategory extends Model
{
    /** @use HasFactory<\Figurate\FulfillmentManager\Database\Factories\ServiceCategoryFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
    ];

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'profile_service_category');
    }
}
