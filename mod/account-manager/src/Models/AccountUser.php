<?php

namespace Figurate\AccountManager\Models;

use App\Models\Server\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountUser extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'user_id',
        'type',
        'is_primary',
        'linked_at',
        'unlinked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'user_id' => 'integer',
            'is_primary' => 'boolean',
            'linked_at' => 'datetime',
            'unlinked_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
