<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Builder;

class Dispute extends Thread
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'threadable_type',
        'threadable_id',
        'purpose',
        'title',
        'phase',
        'status',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('dispute_phase', function (Builder $builder): void {
            $builder->where('purpose', self::PurposeDispute);
        });

        static::creating(function (Dispute $dispute): void {
            if (! $dispute->status) {
                $dispute->status = 'open';
            }

            if (! $dispute->phase) {
                $dispute->phase = 'opened';
            }

            if (! $dispute->purpose) {
                $dispute->purpose = self::PurposeDispute;
            }

            if (! $dispute->title) {
                $dispute->title = 'Dispute';
            }
        });
    }

    public function getOrderAttribute(): ?Order
    {
        if ($this->threadable instanceof Order) {
            return $this->threadable;
        }

        if ($this->threadable_type !== (new Order)->getMorphClass()) {
            return null;
        }

        $orderId = $this->threadable_id;

        return is_numeric($orderId) ? Order::query()->find((int) $orderId) : null;
    }

    public function getOrderIdAttribute(): ?int
    {
        $id = $this->order?->id ?? $this->threadable_id;

        return is_numeric($id) ? (int) $id : null;
    }

    public function getOpenedByIdAttribute(): ?int
    {
        return null;
    }

    public function getResolvedByIdAttribute(): ?int
    {
        return null;
    }

    public function getOpenedByAttribute(): ?int
    {
        return $this->openedById;
    }

    public function getResolvedByAttribute(): ?int
    {
        return $this->resolvedById;
    }

    public function getReasonAttribute(): ?string
    {
        return $this->title;
    }

    public function getResolvedAtAttribute(): mixed
    {
        return null;
    }

    public function setOrderIdAttribute(?int $value): void
    {
        $this->threadable_type = (new Order)->getMorphClass();
        $this->threadable_id = $value;
    }

    public function setOpenedByAttribute(?int $value): void {}

    public function setResolvedByAttribute(?int $value): void
    {
        // Dispute resolution is represented by thread status/phase updates.
    }

    public function setReasonAttribute(?string $value): void
    {
        $this->title = $value ?? 'Dispute';
    }

    public function setResolvedAtAttribute(mixed $value): void
    {
        // Dispute resolution is represented by thread status/phase updates.
    }
}
