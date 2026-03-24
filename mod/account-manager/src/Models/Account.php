<?php

namespace Figurate\AccountManager\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\Server\Identity;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Account extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory, HasPublicUuid;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'status',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_users')
            ->withPivot(['type', 'is_primary', 'linked_at', 'unlinked_at'])
            ->withTimestamps();
    }

    public function activeUsers(): BelongsToMany
    {
        return $this->users()->wherePivotNull('unlinked_at');
    }

    public function identities(): MorphToMany
    {
        return $this->morphToMany(Identity::class, 'relatable', 'identity_relations')
            ->withPivot(['type', 'payload', 'linked_at', 'unlinked_at'])
            ->withTimestamps();
    }
}
