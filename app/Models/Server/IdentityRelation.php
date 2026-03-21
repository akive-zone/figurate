<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IdentityRelation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'identity_id',
        'relatable_type',
        'relatable_id',
        'relationship',
        'payload',
        'linked_at',
        'unlinked_at',
    ];

    public function identity(): BelongsTo
    {
        return $this->belongsTo(Identity::class);
    }

    public function relatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'linked_at' => 'datetime',
            'unlinked_at' => 'datetime',
        ];
    }
}
