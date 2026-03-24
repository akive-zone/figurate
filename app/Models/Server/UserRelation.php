<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRelation extends Model
{
    public const TypeCreator = 'creator';

    public const TypeOwner = 'owner';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'source_user_id',
        'target_user_id',
        'type',
        'payload',
        'linked_at',
        'unlinked_at',
    ];

    public function sourceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_user_id' => 'integer',
            'target_user_id' => 'integer',
            'payload' => 'array',
            'linked_at' => 'datetime',
            'unlinked_at' => 'datetime',
        ];
    }
}
