<?php

namespace App\Models\Server\Fulfillment;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Server\Profile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCategory extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceCategoryFactory> */
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
