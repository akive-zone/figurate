<?php

namespace App\Models\Server\Fulfillment;

use App\Models\Server\Fulfillment\Concerns\HasPostMorphType;
use App\Models\Server\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class Assessment extends Post
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
        static::addGlobalScope('assessment_type', function (Builder $builder): void {
            $builder->where('type', 'like', 'assessment.%');
        });

        static::creating(function (Assessment $assessment): void {
            if (! $assessment->type) {
                $assessment->type = 'assessment.upserted';
            }

            if (! $assessment->occurred_at) {
                $assessment->occurred_at = now();
            }
        });

        static::created(function (Assessment $assessment): void {
            $orderId = data_get($assessment->meta, 'order_id');

            if (! is_numeric($orderId) || $assessment->order) {
                return;
            }

            $order = Order::query()->find((int) $orderId);

            if ($order) {
                $assessment->attachRelation($order, 'order');
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

    public function getNotesAttribute(): ?string
    {
        return data_get($this->payload, 'notes');
    }

    public function getAcknowledgedAtAttribute(): ?Carbon
    {
        $value = data_get($this->payload, 'acknowledged_at');

        return is_string($value) ? Carbon::parse($value) : null;
    }

    public function setOrderIdAttribute(?int $value): void
    {
        $this->putMetaValue('order_id', $value);
    }

    public function setNotesAttribute(?string $value): void
    {
        $this->putPayloadValue('notes', $value);
    }

    public function setAcknowledgedAtAttribute(mixed $value): void
    {
        $serialized = $value instanceof Carbon ? $value->toIso8601String() : $value;
        $this->putPayloadValue('acknowledged_at', $serialized);
    }
}
