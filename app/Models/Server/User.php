<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
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
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class, 'requester_id');
    }

    public function ratingsGiven(): HasMany
    {
        return $this->hasMany(Rating::class, 'rater_id');
    }

    public function ratingsReceived(): HasMany
    {
        return $this->hasMany(Rating::class, 'rated_id');
    }

    public function disputesOpened(): HasMany
    {
        return $this->hasMany(Dispute::class, 'opened_by');
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
