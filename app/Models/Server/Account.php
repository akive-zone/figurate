<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Account extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>> */
    use HasFactory, HasPublicUuid;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'email',
        'password',
        'provider',
        'provider_id',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_users')
            ->withPivot(['relationship', 'is_primary', 'linked_at', 'unlinked_at'])
            ->withTimestamps();
    }

    public function activeUsers(): BelongsToMany
    {
        return $this->users()->wherePivotNull('unlinked_at');
    }
}
