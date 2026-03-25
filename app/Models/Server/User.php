<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;

class User extends Authenticatable implements HasPasskeys
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicUuid, InteractsWithPasskeys, Notifiable;

    public const TypeRobot = 'robot';

    public const TypeWidget = 'widget';

    public const TypeSubject = 'subject';

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
        'status',
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
        return $this->hasMany(Inbox::class, 'user_id');
    }

    public function userAgents(): HasMany
    {
        return $this->hasMany(UserAgent::class, 'user_id');
    }

    public function identities(): MorphToMany
    {
        return $this->morphToMany(Identity::class, 'relatable', 'identity_relations')
            ->withPivot(['type', 'payload', 'linked_at', 'unlinked_at'])
            ->withTimestamps();
    }

    public function outgoingUserRelations(): HasMany
    {
        return $this->hasMany(UserRelation::class, 'source_user_id');
    }

    public function incomingUserRelations(): HasMany
    {
        return $this->hasMany(UserRelation::class, 'target_user_id');
    }

    public function latestUserAgent(): HasOne
    {
        return $this->userAgents()->one()->latestOfMany('last_seen_at');
    }

    public function currentDeviceIdentifier(): ?string
    {
        if ($this->relationLoaded('userAgents')) {
            $deviceIdentifier = $this->userAgents
                ->sortByDesc(fn (UserAgent $userAgent): int => $userAgent->last_seen_at?->getTimestamp() ?? 0)
                ->pluck('device_identifier')
                ->first(fn (?string $value): bool => is_string($value) && trim($value) !== '');

            if (is_string($deviceIdentifier) && trim($deviceIdentifier) !== '') {
                return $deviceIdentifier;
            }
        }

        $deviceIdentifier = $this->userAgents()
            ->whereNotNull('device_identifier')
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->value('device_identifier');

        if (is_string($deviceIdentifier) && trim($deviceIdentifier) !== '') {
            return $deviceIdentifier;
        }

        return null;
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

    public function isRobot(): bool
    {
        return $this->type === self::TypeRobot;
    }

    public function isWidget(): bool
    {
        return $this->type === self::TypeWidget;
    }

    public function isSubject(): bool
    {
        return $this->type === self::TypeSubject;
    }

    public function canActAsHuman(): bool
    {
        return $this->isSubject();
    }

    public function canActAsEndUser(): bool
    {
        return $this->isWidget() || $this->canActAsHuman();
    }

    public function canAccessMarketplace(): bool
    {
        return $this->canActAsEndUser();
    }

    public function canUseInteractiveTransport(): bool
    {
        return $this->isRobot() || $this->canActAsEndUser();
    }

    public function receivesBroadcastNotificationsOn(): string
    {
        return 'users.'.$this->uuid.'.notifications';
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
