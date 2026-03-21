<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\Server\IdentityFactory;
use Figurate\AccountManager\Models\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphedByMany;

class Identity extends Model
{
    /** @use HasFactory<IdentityFactory> */
    use HasFactory, HasPublicUuid;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'provider',
        'provider_subject',
        'payload',
        'linked_at',
        'last_used_at',
    ];

    public function users(): MorphedByMany
    {
        return $this->morphedByMany(User::class, 'relatable', 'identity_relations')
            ->withPivot(['relationship', 'payload', 'linked_at', 'unlinked_at'])
            ->withTimestamps();
    }

    public function accounts(): MorphedByMany
    {
        return $this->morphedByMany(Account::class, 'relatable', 'identity_relations')
            ->withPivot(['relationship', 'payload', 'linked_at', 'unlinked_at'])
            ->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'linked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }
}
