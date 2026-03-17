<?php

namespace App\Models\Server;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public const TypeRobot = 'robot';

    public const TypeGadget = 'gadget';

    public const TypeSubject = 'subject';

    public const TypePerson = 'person';

    public const TypeSystem = 'system';

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
        return $this->hasMany(Inbox::class, 'user_id');
    }

    public function accountUsers(): HasMany
    {
        return $this->hasMany(AccountUser::class, 'user_id');
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_users', 'user_id', 'account_id')
            ->withPivot(['relationship', 'is_primary', 'linked_at', 'unlinked_at'])
            ->withTimestamps();
    }

    public function activeAccounts(): BelongsToMany
    {
        return $this->accounts()->wherePivotNull('unlinked_at');
    }

    public function userAgents(): HasMany
    {
        return $this->hasMany(UserAgent::class, 'user_id');
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

        return is_string($this->device_identifier) && trim($this->device_identifier) !== ''
            ? $this->device_identifier
            : null;
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
        return in_array($this->type, [self::TypeRobot, 'agent'], true);
    }

    public function isGadget(): bool
    {
        return in_array($this->type, [self::TypeGadget, 'device'], true);
    }

    public function isSubject(): bool
    {
        return in_array($this->type, [self::TypeSubject, self::TypePerson], true);
    }

    public function isLegacyPerson(): bool
    {
        return $this->type === self::TypePerson;
    }

    public function isSystem(): bool
    {
        return $this->type === self::TypeSystem;
    }

    public function hasAccount(): bool
    {
        if ($this->relationLoaded('accounts')) {
            return $this->accounts->contains(function (Account $account): bool {
                return $account->pivot === null || $account->pivot->unlinked_at === null;
            });
        }

        return $this->activeAccounts()->exists();
    }

    public function primaryAccount(): ?Account
    {
        return $this->activeAccounts()
            ->orderByPivotDesc('is_primary')
            ->orderByPivotDesc('linked_at')
            ->first();
    }

    public function canActAsHuman(): bool
    {
        return $this->isSubject() || $this->hasAccount();
    }

    public function canUseInteractiveTransport(): bool
    {
        return $this->isSystem() || $this->isRobot() || $this->canActAsHuman();
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
