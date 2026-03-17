<?php

namespace Figurate\FulfillmentManager\Models;

use App\Models\Server\Post;
use App\Models\Server\Profile;
use App\Models\Server\User;
use Figurate\FulfillmentManager\Database\Factories\OrderFactory;
use Figurate\FulfillmentManager\Models\Concerns\HasPostMorphType;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

#[UseFactory(OrderFactory::class)]
class Order extends Post
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
        static::addGlobalScope('order_type', function (Builder $builder): void {
            $builder->where('type', 'like', 'order.%');
        });

        static::creating(function (Order $order): void {
            if (! $order->type) {
                $order->type = 'order.booked';
            }

            if (! $order->occurred_at) {
                $order->occurred_at = now();
            }
        });

        static::created(function (Order $order): void {
            $relations = [
                'request_id' => [Request::class, 'request'],
                'quote_id' => [Quote::class, 'quote'],
                'buyer_id' => [User::class, 'buyer'],
                'seller_profile_id' => [Profile::class, 'seller_profile'],
            ];

            foreach ($relations as $metaKey => [$modelClass, $role]) {
                $relatedId = data_get($order->meta, $metaKey);

                if (! is_numeric($relatedId)) {
                    continue;
                }

                $existing = $order->relatedOne($modelClass, $role);

                if ($existing) {
                    continue;
                }

                $relatedModel = $modelClass::query()->find((int) $relatedId);

                if ($relatedModel) {
                    $order->attachRelation($relatedModel, $role);
                }
            }
        });
    }

    public function requestRecord(): ?Request
    {
        return $this->relatedOne(Request::class, 'request');
    }

    public function quoteRecord(): ?Quote
    {
        return $this->relatedOne(Quote::class, 'quote');
    }

    public function buyerRecord(): ?User
    {
        return $this->relatedOne(User::class, 'buyer');
    }

    public function sellerProfileRecord(): ?Profile
    {
        return $this->relatedOne(Profile::class, 'seller_profile');
    }

    public function assessment(): ?Assessment
    {
        return Assessment::query()->whereHas('relations', function (Builder $query): void {
            $query->where('relationable_type', $this->getMorphClass())
                ->where('relationable_id', $this->getKey())
                ->where('role', 'order');
        })->latest('id')->first();
    }

    /**
     * @return Collection<int, Process>
     */
    public function processes(): Collection
    {
        return Process::query()->whereHas('relations', function (Builder $query): void {
            $query->where('relationable_type', $this->getMorphClass())
                ->where('relationable_id', $this->getKey())
                ->where('role', 'order');
        })->latest('id')->get();
    }

    /**
     * @return Collection<int, Payment>
     */
    public function payments(): Collection
    {
        return Payment::query()->whereHas('relations', function (Builder $query): void {
            $query->where('relationable_type', $this->getMorphClass())
                ->where('relationable_id', $this->getKey())
                ->where('role', 'order');
        })->latest('id')->get();
    }

    public function getRequestAttribute(): ?Request
    {
        return $this->requestRecord();
    }

    public function getQuoteAttribute(): ?Quote
    {
        return $this->quoteRecord();
    }

    public function getBuyerAttribute(): ?User
    {
        return $this->buyerRecord();
    }

    public function getSellerProfileAttribute(): ?Profile
    {
        return $this->sellerProfileRecord();
    }

    public function getRequestIdAttribute(): ?int
    {
        return $this->requestRecord()?->id;
    }

    public function getQuoteIdAttribute(): ?int
    {
        return $this->quoteRecord()?->id;
    }

    public function getBuyerIdAttribute(): ?int
    {
        return $this->buyerRecord()?->id;
    }

    public function getSellerProfileIdAttribute(): ?int
    {
        return $this->sellerProfileRecord()?->id;
    }

    public function setRequestIdAttribute(?int $value): void
    {
        $this->putMetaValue('request_id', $value);
    }

    public function setQuoteIdAttribute(?int $value): void
    {
        $this->putMetaValue('quote_id', $value);
    }

    public function setBuyerIdAttribute(?int $value): void
    {
        $this->putMetaValue('buyer_id', $value);
    }

    public function setSellerProfileIdAttribute(?int $value): void
    {
        $this->putMetaValue('seller_profile_id', $value);
    }
}
