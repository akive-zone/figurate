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
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Post extends Model implements HasMedia
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasUlids, InteractsWithMedia, SoftDeletes;

    public const TypeMessage = 'message';

    public const RelationRoleSender = 'sender';

    public const StatusActive = 'active';

    public const AttachmentCollection = 'attachments';

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
        'text',
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
                'data' => json_encode(array_merge(is_array($this->data) ? $this->data : [], ['text' => $value])) ?: null,
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
                if ($this->relationLoaded('senderRelation')) {
                    return $this->senderRelation?->relationable_type;
                }

                if (self::getConnectionResolver() === null) {
                    return null;
                }

                return $this->senderRelation()->first()?->relationable_type;
            },
        );
    }

    protected function senderableId(): Attribute
    {
        return Attribute::make(
            get: function (): mixed {
                if ($this->relationLoaded('senderRelation')) {
                    return $this->senderRelation?->relationable_id;
                }

                if (self::getConnectionResolver() === null) {
                    return null;
                }

                return $this->senderRelation()->first()?->relationable_id;
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

    protected function attachments(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): array {
                $media = $this->attachmentMedia();

                if ($media !== []) {
                    return $media;
                }

                if (is_array($value)) {
                    return $value;
                }

                $decoded = json_decode((string) ($attributes['attachments'] ?? 'null'), true);

                return is_array($decoded) ? $decoded : [];
            },
            set: fn (mixed $value): array => [
                'attachments' => is_array($value) && $value !== [] ? json_encode($value) : null,
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::AttachmentCollection);
    }

    public function relations(): HasMany
    {
        return $this->hasMany(PostRelation::class, 'post_id');
    }

    public function messageable(): MorphTo
    {
        return $this->postable();
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

    public function scopeMessageType(Builder $query): Builder
    {
        return $query->where('type', self::TypeMessage);
    }

    public function scopeForThread(Builder $query, Thread $thread): Builder
    {
        return $query
            ->messageType()
            ->where('postable_type', $thread->getMorphClass())
            ->where('postable_id', $thread->getKey());
    }

    public function scopeFromSender(Builder $query, EloquentModel $sender): Builder
    {
        return $query->whereHas('senderRelation', function (Builder $relationQuery) use ($sender): void {
            $relationQuery
                ->where('relationable_type', $sender->getMorphClass())
                ->where('relationable_id', $sender->getKey())
                ->where('role', self::RelationRoleSender);
        });
    }

    public function scopeWithoutSender(Builder $query): Builder
    {
        return $query->whereDoesntHave('senderRelation');
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
        if ($this->relationLoaded('senderRelation')) {
            return $this->senderRelation?->relationable;
        }

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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function attachmentMedia(): array
    {
        if (! app()->bound('config') || self::getConnectionResolver() === null) {
            return [];
        }

        $mediaItems = $this->relationLoaded('media')
            ? $this->media->where('collection_name', self::AttachmentCollection)->values()
            : $this->getMedia(self::AttachmentCollection);

        return $mediaItems
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'name' => $media->name ?: pathinfo($media->file_name, PATHINFO_FILENAME),
                'file_name' => $media->file_name,
                'mime' => $media->mime_type,
                'size' => $media->size,
                'url' => $media->getUrl(),
                'path' => $media->getPath(),
            ])
            ->values()
            ->all();
    }
}
