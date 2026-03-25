<?php

namespace App\Models\Server;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    public const TypeMessage = 'message';

    public const RelationRoleSender = 'sender';

    public const StatusActive = 'active';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'postable_type',
        'postable_id',
        'type',
        'tag',
        'status',
        'data',
        'payload',
        'attachments',
        'actions',
        'errors',
        'meta',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'attachments' => 'array',
            'actions' => 'array',
            'errors' => 'array',
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected function text(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => is_array($this->data) ? (isset($this->data['text']) ? (string) $this->data['text'] : null) : null,
            set: fn (mixed $value): array => [
                'data' => array_merge(is_array($this->data) ? $this->data : [], ['text' => $value]),
            ],
        );
    }

    protected function messageableType(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->postable_type,
        );
    }

    protected function messageableId(): Attribute
    {
        return Attribute::make(
            get: fn (): mixed => $this->postable_id,
        );
    }

    protected function senderableType(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if ($this->relationLoaded('senderRelation') && $this->senderRelation !== null) {
                    return $this->senderRelation->relationable_type;
                }

                return null;
            },
        );
    }

    protected function senderableId(): Attribute
    {
        return Attribute::make(
            get: function (): mixed {
                if ($this->relationLoaded('senderRelation') && $this->senderRelation !== null) {
                    return $this->senderRelation->relationable_id;
                }

                return null;
            },
        );
    }

    protected function payload(): Attribute
    {
        return Attribute::make(
            get: fn (): ?array => is_array($this->data) ? $this->data : null,
            set: fn (mixed $value): array => [
                'data' => is_array($value) ? (json_encode($value) ?: null) : null,
            ],
        );
    }

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function relations(): HasMany
    {
        return $this->hasMany(PostRelation::class, 'post_id');
    }

    public function postable(): MorphTo
    {
        return $this->morphTo();
    }

    public function senderRelation(): HasOne
    {
        return $this->hasOne(PostRelation::class, 'post_id')
            ->where('role', self::RelationRoleSender)
            ->latestOfMany();
    }

    public function posts(): MorphMany
    {
        return $this->morphMany(self::class, 'postable');
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

    public function sender(): ?EloquentModel
    {
        return $this->senderRelation()->first()?->relationable;
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
