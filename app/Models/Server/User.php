<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;

class User extends Authenticatable implements HasPasskeys
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasPublicUuid, InteractsWithPasskeys, Notifiable;

    protected $table = 'users';

    protected mixed $resolvedAccessToken = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'email',
        'password',
        'type',
        'provider',
        'provider_id',
        'status',
        'device_identifier',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function messages(): MorphMany
    {
        return $this->morphMany(Message::class, 'senderable');
    }

    public function contextServers(): MorphMany
    {
        return $this->morphMany(ContextServer::class, 'contextable');
    }

    public function inboxes(): HasMany
    {
        return $this->hasMany(Inbox::class);
    }

    public function withAccessToken($accessToken)
    {
        $this->resolvedAccessToken = $accessToken;

        return $this;
    }

    public function currentAccessToken()
    {
        return $this->resolvedAccessToken;
    }

    public function tokenCan(string $ability)
    {
        return is_object($this->resolvedAccessToken)
            && method_exists($this->resolvedAccessToken, 'can')
            && (bool) $this->resolvedAccessToken->can($ability);
    }

    public function tokenCant(string $ability)
    {
        return ! $this->tokenCan($ability);
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
