<?php

namespace App\Ai\Support\Fulfillment;

use App\Models\Server\Fulfillment\Assessment;
use App\Models\Server\Fulfillment\Order;
use App\Models\Server\Fulfillment\Quote;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EloquentFulfillmentGateway implements FulfillmentGateway
{
    public function currentOrder(Post $requestPost): mixed
    {
        return Order::query()
            ->whereHas('relations', function (Builder $query) use ($requestPost): void {
                $query->where('relationable_type', $requestPost->getMorphClass())
                    ->where('relationable_id', $requestPost->getKey())
                    ->where('role', 'request');
            })
            ->latest('id')
            ->first();
    }

    public function quoteForRequest(Post $requestPost, int $quoteId): mixed
    {
        return $this->quotesForRequest($requestPost)
            ->whereKey($quoteId)
            ->first();
    }

    public function createOrderFromQuote(Thread $thread, Post $requestPost, User $actor, int $quoteId, string $status): array
    {
        /** @var Quote|null $quote */
        $quote = $this->quoteForRequest($requestPost, $quoteId);

        if (! $quote) {
            return ['ok' => false, 'error' => 'Quote not found for this request.'];
        }

        /** @var Order|null $existingOrder */
        $existingOrder = $this->currentOrder($requestPost);

        if ($existingOrder) {
            return [
                'ok' => true,
                'created' => false,
                'order_id' => $existingOrder->id,
                'status' => $existingOrder->status,
                'message' => 'Order already exists for this request.',
            ];
        }

        /** @var Order $order */
        $order = DB::transaction(function () use ($actor, $quote, $requestPost, $status, $thread): Order {
            $quote->forceFill([
                'status' => 'accepted',
            ])->save();

            $this->quotesForRequest($requestPost)
                ->whereKeyNot($quote->id)
                ->where('status', 'pending')
                ->update(['status' => 'rejected']);

            $order = Order::query()->create([
                'type' => 'order.booked',
                'status' => $status,
                'payload' => [
                    'buyer_id' => $actor->id,
                    'seller_profile_id' => $quote->profile_id,
                ],
                'meta' => [
                    'source' => 'tool.create_order_from_quote',
                ],
                'occurred_at' => now(),
            ]);

            $order->attachRelation($thread, 'primary');
            $order->attachRelation($requestPost, 'request');
            $order->attachRelation($quote, 'quote');
            $order->attachRelation($actor, 'buyer');

            if ($quote->profile) {
                $order->attachRelation($quote->profile, 'seller_profile');
            }

            $requestPost->forceFill([
                'status' => $status,
            ])->save();

            $thread->messages()->create([
                'senderable_type' => null,
                'senderable_id' => null,
                'type' => 'system',
                'tag' => 'order_created',
                'body' => "Order #{$order->id} was created from Quote #{$quote->id}.",
                'attachments' => null,
                'meta' => [
                    'source' => 'tool',
                    'tool' => 'fulfillment.gateway.create_order_from_quote',
                    'order_id' => $order->id,
                    'quote_id' => $quote->id,
                ],
            ]);

            return $order;
        });

        return [
            'ok' => true,
            'created' => true,
            'order_id' => $order->id,
            'quote_id' => $quote->id,
            'status' => $order->status,
        ];
    }

    public function acknowledgeAssessment(Thread $thread, Post $requestPost, User $actor, ?string $note = null): array
    {
        /** @var Order|null $order */
        $order = $this->currentOrder($requestPost);

        if (! $order) {
            return ['ok' => false, 'error' => 'No order exists for this request.'];
        }

        /** @var Assessment|null $assessment */
        $assessment = $order->assessment();

        if (! $assessment) {
            return ['ok' => false, 'error' => 'No assessment exists for this order.'];
        }

        $assessment->forceFill([
            'type' => 'assessment.acknowledged',
            'status' => 'acknowledged',
            'payload' => array_merge($assessment->payload ?? [], [
                'acknowledged_at' => now()->toIso8601String(),
            ]),
            'meta' => array_merge($assessment->meta ?? [], [
                'source' => 'tool.acknowledge_assessment',
            ]),
            'occurred_at' => now(),
        ])->save();

        $order->forceFill([
            'status' => 'assessment_acknowledged',
        ])->save();

        $normalizedNote = trim((string) $note);

        $thread->messages()->create([
            'senderable_type' => null,
            'senderable_id' => null,
            'type' => 'system',
            'tag' => 'assessment_acknowledged',
            'body' => $normalizedNote !== ''
                ? "Assessment #{$assessment->id} acknowledged. {$normalizedNote}"
                : "Assessment #{$assessment->id} acknowledged.",
            'attachments' => null,
            'meta' => [
                'source' => 'tool',
                'tool' => 'fulfillment.gateway.acknowledge_assessment',
                'order_id' => $order->id,
                'assessment_id' => $assessment->id,
            ],
        ]);

        return [
            'ok' => true,
            'assessment_id' => $assessment->id,
            'order_id' => $order->id,
            'status' => $assessment->status,
        ];
    }

    public function upsertAssessment(Thread $thread, Post $requestPost, User $actor, string $notes, string $status): array
    {
        /** @var Order|null $order */
        $order = $this->currentOrder($requestPost);

        if (! $order) {
            return ['ok' => false, 'error' => 'No order exists for this request.'];
        }

        if ($order->sellerProfile?->user_id !== $actor->id) {
            return ['ok' => false, 'error' => 'Only the assigned worker can upsert assessments.'];
        }

        /** @var Assessment $assessment */
        $assessment = $order->assessment() ?? new Assessment;

        $assessment->fill([
            'type' => 'assessment.upserted',
            'status' => $status,
            'payload' => [
                'notes' => $notes !== '' ? $notes : null,
                'acknowledged_at' => $status === 'acknowledged' ? now()?->toIso8601String() : null,
            ],
            'meta' => [
                'source' => 'tool.upsert_assessment',
            ],
            'occurred_at' => now(),
        ]);
        $assessment->save();

        if (! $assessment->relatedOne(Order::class, 'order')) {
            $assessment->attachRelation($order, 'order');
        }

        if (! $assessment->relatedOne(Thread::class, 'primary')) {
            $assessment->attachRelation($thread, 'primary');
        }

        if (! $assessment->relatedOne($requestPost::class, 'request')) {
            $assessment->attachRelation($requestPost, 'request');
        }

        $order->forceFill([
            'status' => $status === 'acknowledged' ? 'assessment_acknowledged' : 'assessment_pending_ack',
        ])->save();

        $thread->messages()->create([
            'senderable_type' => null,
            'senderable_id' => null,
            'type' => 'system',
            'tag' => 'assessment_upserted',
            'body' => "Assessment #{$assessment->id} saved with status {$assessment->status}.",
            'attachments' => null,
            'meta' => [
                'source' => 'tool',
                'tool' => 'fulfillment.gateway.upsert_assessment',
                'order_id' => $order->id,
                'assessment_id' => $assessment->id,
            ],
        ]);

        return [
            'ok' => true,
            'assessment_id' => $assessment->id,
            'order_id' => $order->id,
            'status' => $assessment->status,
        ];
    }

    protected function quotesForRequest(Post $requestPost): Builder
    {
        return Quote::query()->whereHas('relations', function (Builder $query) use ($requestPost): void {
            $query->where('relationable_type', $requestPost->getMorphClass())
                ->where('relationable_id', $requestPost->getKey())
                ->where('role', 'request');
        });
    }
}
