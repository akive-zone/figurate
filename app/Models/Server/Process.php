<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Builder;

class Process extends Post
{
    protected $table = 'posts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'type',
        'status',
        'payload',
        'meta',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('process_type', function (Builder $builder): void {
            $builder->where('type', 'like', 'process.%');
        });

        static::creating(function (Process $process): void {
            if (! $process->type) {
                $process->type = 'process.logged';
            }

            if (! $process->occurred_at) {
                $process->occurred_at = now();
            }
        });

        static::created(function (Process $process): void {
            $orderId = data_get($process->meta, 'order_id');
            $profileId = data_get($process->meta, 'profile_id');

            if (is_numeric($orderId) && ! $process->order) {
                $order = Order::query()->find((int) $orderId);

                if ($order) {
                    $process->attachRelation($order, 'order');
                }
            }

            if (is_numeric($profileId) && ! $process->profile) {
                $profile = Profile::query()->find((int) $profileId);

                if ($profile) {
                    $process->attachRelation($profile, 'profile');
                }
            }
        });
    }

    public function getOrderAttribute(): ?Order
    {
        return $this->relatedOne(Order::class, 'order');
    }

    public function getProfileAttribute(): ?Profile
    {
        return $this->relatedOne(Profile::class, 'profile');
    }

    public function getOrderIdAttribute(): ?int
    {
        return $this->order?->id;
    }

    public function getProfileIdAttribute(): ?int
    {
        return $this->profile?->id;
    }

    public function getContentAttribute(): ?string
    {
        return data_get($this->payload, 'content');
    }

    public function getKindAttribute(): ?string
    {
        return data_get($this->payload, 'kind');
    }

    public function setOrderIdAttribute(?int $value): void
    {
        $this->putMetaValue('order_id', $value);
    }

    public function setProfileIdAttribute(?int $value): void
    {
        $this->putMetaValue('profile_id', $value);
    }

    public function setContentAttribute(?string $value): void
    {
        $this->putPayloadValue('content', $value);
    }

    public function setKindAttribute(?string $value): void
    {
        $this->putPayloadValue('kind', $value);
    }
}
