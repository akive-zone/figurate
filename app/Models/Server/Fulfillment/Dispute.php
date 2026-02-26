<?php

namespace App\Models\Server\Fulfillment;

use App\Models\Server\Fulfillment\Concerns\HasPostMorphType;
use App\Models\Server\Post;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class Dispute extends Post
{
    use HasPostMorphType;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'postable_type',
        'postable_id',
        'type',
        'status',
        'payload',
        'meta',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('dispute_type', function (Builder $builder): void {
            $builder->where('type', 'like', 'dispute.%');
        });

        static::creating(function (Dispute $dispute): void {
            if (! $dispute->type) {
                $dispute->type = 'dispute.opened';
            }

            if (! $dispute->status) {
                $dispute->status = 'open';
            }

            if (! $dispute->occurred_at) {
                $dispute->occurred_at = now();
            }
        });

        static::created(function (Dispute $dispute): void {
            $orderId = data_get($dispute->meta, 'order_id');

            if (is_numeric($orderId) && ! $dispute->order) {
                $order = Order::query()->find((int) $orderId);

                if ($order) {
                    $dispute->attachRelation($order, 'order');
                }
            }
        });
    }

    public function getOrderAttribute(): ?Order
    {
        return $this->relatedOne(Order::class, 'order');
    }

    public function getOrderIdAttribute(): ?int
    {
        return $this->order?->id;
    }

    public function getOpenedByIdAttribute(): ?int
    {
        $value = data_get($this->meta, 'opened_by');

        return is_numeric($value) ? (int) $value : null;
    }

    public function getResolvedByIdAttribute(): ?int
    {
        $value = data_get($this->meta, 'resolved_by');

        return is_numeric($value) ? (int) $value : null;
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
        return data_get($this->payload, 'reason');
    }

    public function getResolvedAtAttribute(): ?Carbon
    {
        $value = data_get($this->payload, 'resolved_at');

        return is_string($value) ? Carbon::parse($value) : null;
    }

    public function setOrderIdAttribute(?int $value): void
    {
        $this->putMetaValue('order_id', $value);
    }

    public function setOpenedByAttribute(?int $value): void
    {
        $this->putMetaValue('opened_by', $value);
    }

    public function setResolvedByAttribute(?int $value): void
    {
        $this->putMetaValue('resolved_by', $value);
    }

    public function setReasonAttribute(?string $value): void
    {
        $this->putPayloadValue('reason', $value);
    }

    public function setResolvedAtAttribute(mixed $value): void
    {
        $serialized = $value instanceof Carbon ? $value->toIso8601String() : $value;
        $this->putPayloadValue('resolved_at', $serialized);
    }

    public function opener(): ?User
    {
        $id = $this->openedById;

        return $id ? User::query()->find($id) : null;
    }
}
