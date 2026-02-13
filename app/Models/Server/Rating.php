<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Builder;

class Rating extends Post
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
        static::addGlobalScope('rating_type', function (Builder $builder): void {
            $builder->where('type', 'like', 'rating.%');
        });

        static::creating(function (Rating $rating): void {
            if (! $rating->type) {
                $rating->type = 'rating.created';
            }

            if (! $rating->status) {
                $rating->status = 'recorded';
            }

            if (! $rating->occurred_at) {
                $rating->occurred_at = now();
            }
        });

        static::created(function (Rating $rating): void {
            $relations = [
                'order_id' => [Order::class, 'order'],
                'rater_id' => [User::class, 'rater'],
                'rated_id' => [User::class, 'rated'],
            ];

            foreach ($relations as $metaKey => [$modelClass, $role]) {
                $relatedId = data_get($rating->meta, $metaKey);

                if (! is_numeric($relatedId)) {
                    continue;
                }

                if ($rating->relatedOne($modelClass, $role)) {
                    continue;
                }

                $relatedModel = $modelClass::query()->find((int) $relatedId);

                if ($relatedModel) {
                    $rating->attachRelation($relatedModel, $role);
                }
            }
        });
    }

    public function getOrderAttribute(): ?Order
    {
        $related = $this->relatedOne(Order::class, 'order');

        if ($related) {
            return $related;
        }

        $orderId = data_get($this->meta, 'order_id');

        return is_numeric($orderId) ? Order::query()->find((int) $orderId) : null;
    }

    public function getRaterAttribute(): ?User
    {
        $related = $this->relatedOne(User::class, 'rater');

        if ($related) {
            return $related;
        }

        $raterId = data_get($this->meta, 'rater_id');

        return is_numeric($raterId) ? User::query()->find((int) $raterId) : null;
    }

    public function getRatedAttribute(): ?User
    {
        $related = $this->relatedOne(User::class, 'rated');

        if ($related) {
            return $related;
        }

        $ratedId = data_get($this->meta, 'rated_id');

        return is_numeric($ratedId) ? User::query()->find((int) $ratedId) : null;
    }

    public function getOrderIdAttribute(): ?int
    {
        $id = $this->order?->id ?? data_get($this->meta, 'order_id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function getRaterIdAttribute(): ?int
    {
        $id = $this->rater?->id ?? data_get($this->meta, 'rater_id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function getRatedIdAttribute(): ?int
    {
        $id = $this->rated?->id ?? data_get($this->meta, 'rated_id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function getScoreAttribute(): ?int
    {
        $value = data_get($this->payload, 'score');

        return is_numeric($value) ? (int) $value : null;
    }

    public function getCommentAttribute(): ?string
    {
        $value = data_get($this->payload, 'comment');

        return is_string($value) ? $value : null;
    }

    public function setOrderIdAttribute(?int $value): void
    {
        $this->putMetaValue('order_id', $value);
    }

    public function setRaterIdAttribute(?int $value): void
    {
        $this->putMetaValue('rater_id', $value);
    }

    public function setRatedIdAttribute(?int $value): void
    {
        $this->putMetaValue('rated_id', $value);
    }

    public function setScoreAttribute(?int $value): void
    {
        $this->putPayloadValue('score', $value);
    }

    public function setCommentAttribute(?string $value): void
    {
        $this->putPayloadValue('comment', $value);
    }
}
