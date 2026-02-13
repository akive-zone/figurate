<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'type',
        'status',
        'payload',
        'meta',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function relations(): HasMany
    {
        return $this->hasMany(PostRelation::class);
    }

    public function attachRelation(EloquentModel $model, string $role = 'context'): PostRelation
    {
        return $this->relations()->create([
            'relationable_type' => $model->getMorphClass(),
            'relationable_id' => $model->getKey(),
            'role' => $role,
        ]);
    }

    /**
     * @param  class-string<EloquentModel>  $modelClass
     */
    public function relatedQuery(string $modelClass, ?string $role = null): Builder
    {
        $instance = new $modelClass;

        return $modelClass::query()
            ->whereIn($instance->getQualifiedKeyName(), function ($query) use ($instance, $role): void {
                $query->from('post_relations')
                    ->select('relationable_id')
                    ->where('post_id', $this->getKey())
                    ->where('relationable_type', $instance->getMorphClass());

                if ($role !== null) {
                    $query->where('role', $role);
                }
            });
    }

    /**
     * @param  class-string<EloquentModel>  $modelClass
     */
    public function relatedOne(string $modelClass, ?string $role = null): ?EloquentModel
    {
        return $this->relatedQuery($modelClass, $role)->first();
    }

    protected function putPayloadValue(string $key, mixed $value): void
    {
        $payload = $this->payload ?? [];
        $payload[$key] = $value;
        $this->payload = $payload;
    }

    protected function putMetaValue(string $key, mixed $value): void
    {
        $meta = $this->meta ?? [];
        $meta[$key] = $value;
        $this->meta = $meta;
    }
}
