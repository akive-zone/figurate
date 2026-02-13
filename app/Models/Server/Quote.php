<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Builder;

class Quote extends Post
{
    protected $table = 'posts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'type',
        'status',
        'payload',
        'meta',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('quote_type', function (Builder $builder): void {
            $builder->where('type', 'like', 'quote.%');
        });

        static::creating(function (Quote $quote): void {
            if (! $quote->type) {
                $quote->type = 'quote.submitted';
            }

            if (! $quote->occurred_at) {
                $quote->occurred_at = now();
            }
        });

        static::created(function (Quote $quote): void {
            $requestId = data_get($quote->meta, 'request_id');
            $profileId = data_get($quote->meta, 'profile_id');

            if (is_numeric($requestId) && ! $quote->requestRecord()) {
                $request = Request::query()->find((int) $requestId);

                if ($request) {
                    $quote->attachRelation($request, 'request');
                }
            }

            if (is_numeric($profileId) && ! $quote->profileRecord()) {
                $profile = Profile::query()->find((int) $profileId);

                if ($profile) {
                    $quote->attachRelation($profile, 'profile');
                }
            }
        });
    }

    public function requestRecord(): ?Request
    {
        return $this->relatedOne(Request::class, 'request');
    }

    public function profileRecord(): ?Profile
    {
        return $this->relatedOne(Profile::class, 'profile');
    }

    public function getRequestAttribute(): ?Request
    {
        return $this->requestRecord();
    }

    public function getProfileAttribute(): ?Profile
    {
        return $this->profileRecord();
    }

    public function getRequestIdAttribute(): ?int
    {
        return $this->requestRecord()?->id;
    }

    public function getProfileIdAttribute(): ?int
    {
        return $this->profileRecord()?->id;
    }

    public function getAmountAttribute(): ?string
    {
        $amount = data_get($this->payload, 'amount');

        return $amount === null ? null : (string) $amount;
    }

    public function getCurrencyAttribute(): ?string
    {
        return data_get($this->payload, 'currency');
    }

    public function getDetailsAttribute(): ?string
    {
        return data_get($this->payload, 'details');
    }

    public function setRequestIdAttribute(?int $value): void
    {
        $this->putMetaValue('request_id', $value);
    }

    public function setProfileIdAttribute(?int $value): void
    {
        $this->putMetaValue('profile_id', $value);
    }

    public function setAmountAttribute(mixed $value): void
    {
        $this->putPayloadValue('amount', $value);
    }

    public function setCurrencyAttribute(?string $value): void
    {
        $this->putPayloadValue('currency', $value);
    }

    public function setDetailsAttribute(?string $value): void
    {
        $this->putPayloadValue('details', $value);
    }
}
