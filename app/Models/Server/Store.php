<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Store extends Model implements HasMedia
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory, HasPublicUuid, InteractsWithMedia, SoftDeletes;

    protected $table = 'stores';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'provider',
        'external_store_id',
        'status',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents');
    }

    public function spaces(): MorphToMany
    {
        return $this->morphedByMany(Space::class, 'storeable', 'storeables', 'store_id', 'storeable_id')
            ->withPivot(['scope', 'created_by', 'meta'])
            ->withTimestamps();
    }

    public function threads(): MorphToMany
    {
        return $this->morphedByMany(Thread::class, 'storeable', 'storeables', 'store_id', 'storeable_id')
            ->withPivot(['scope', 'created_by', 'meta'])
            ->withTimestamps();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StoreDocument::class);
    }
}
