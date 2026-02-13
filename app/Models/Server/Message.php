<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Message extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\MessageFactory> */
    use HasFactory, HasUlids, InteractsWithMedia, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'messageable_type',
        'messageable_id',
        'senderable_type',
        'senderable_id',
        'type',
        'body',
        'attachments',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'meta' => 'array',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function messageable(): MorphTo
    {
        return $this->morphTo();
    }

    public function senderable(): MorphTo
    {
        return $this->morphTo();
    }

    public function sender(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'senderable_type', 'senderable_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }

    public function syncAttachmentPayload(): void
    {
        $attachments = $this->getMedia('attachments')
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'name' => $media->name ?: $media->file_name,
                'file_name' => $media->file_name,
                'mime' => $media->mime_type,
                'size' => $media->size,
                'url' => $media->getUrl(),
                'path' => $media->getUrl(),
            ])
            ->values()
            ->all();

        $this->forceFill([
            'attachments' => $attachments !== [] ? $attachments : null,
        ])->save();
    }
}
