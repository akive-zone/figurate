<?php

namespace Figurate\FulfillmentManager\Models;

use App\Models\Server\Post;
use Figurate\FulfillmentManager\Database\Factories\PaymentFactory;
use Figurate\FulfillmentManager\Models\Concerns\HasPostMorphType;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;

#[UseFactory(PaymentFactory::class)]
class Payment extends Post
{
    use HasPostMorphType;

    protected $table = 'posts';

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
        static::addGlobalScope('payment_type', function (Builder $builder): void {
            $builder->where('type', 'like', 'payment.%');
        });

        static::creating(function (Payment $payment): void {
            if (! $payment->type) {
                $payment->type = 'payment.recorded';
            }

            if (! $payment->occurred_at) {
                $payment->occurred_at = now();
            }
        });

        static::created(function (Payment $payment): void {
            $orderId = data_get($payment->meta, 'order_id');

            if (! is_numeric($orderId) || $payment->order) {
                return;
            }

            $order = Order::query()->find((int) $orderId);

            if ($order) {
                $payment->attachRelation($order, 'order');
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

    public function getAmountAttribute(): ?string
    {
        $amount = data_get($this->payload, 'amount');

        return $amount === null ? null : (string) $amount;
    }

    public function getCurrencyAttribute(): ?string
    {
        return data_get($this->payload, 'currency');
    }

    public function getStageAttribute(): ?string
    {
        return data_get($this->payload, 'stage');
    }

    public function getProviderAttribute(): ?string
    {
        return data_get($this->payload, 'provider');
    }

    public function getProviderRefAttribute(): ?string
    {
        return data_get($this->payload, 'provider_ref');
    }

    public function setOrderIdAttribute(?int $value): void
    {
        $this->putMetaValue('order_id', $value);
    }

    public function setAmountAttribute(mixed $value): void
    {
        $this->putPayloadValue('amount', $value);
    }

    public function setCurrencyAttribute(?string $value): void
    {
        $this->putPayloadValue('currency', $value);
    }

    public function setStageAttribute(?string $value): void
    {
        $this->putPayloadValue('stage', $value);
    }

    public function setProviderAttribute(?string $value): void
    {
        $this->putPayloadValue('provider', $value);
    }

    public function setProviderRefAttribute(?string $value): void
    {
        $this->putPayloadValue('provider_ref', $value);
    }
}
