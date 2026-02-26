<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class StoreDocument extends Model
{
    /** @use HasFactory<\Database\Factories\StoreDocumentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id',
        'media_id',
        'message_id',
        'origin',
        'provider_file_id',
        'provider_document_id',
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

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
