<?php

namespace App\Ai\Tools;

use App\Models\Server\Order;
use App\Models\Server\Quote;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Stringable;

class CreateOrderFromQuoteTool implements Tool
{
    public function __construct(
        protected Thread $thread,
        protected ServiceRequest $serviceRequest,
        protected User $actor,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Accept a quote and create the order for this request. Use when the asker confirms a specific quote.';
    }

    /**
     * Execute the tool.
     */
    public function handle(ToolRequest $request): Stringable|string
    {
        if (! $this->serviceRequest->hasUserActor($this->actor, ServiceRequest::ActionAsker)) {
            return $this->encodeError('Only the request asker can create an order.');
        }

        $quoteId = (int) ($request['quote_id'] ?? 0);

        if ($quoteId <= 0) {
            return $this->encodeError('quote_id is required.');
        }

        /** @var Quote|null $quote */
        $quote = $this->serviceRequest->quotes()->whereKey($quoteId)->first();

        if (! $quote) {
            return $this->encodeError('Quote not found for this request.');
        }

        /** @var Order|null $existingOrder */
        $existingOrder = $this->serviceRequest->order()->first();

        if ($existingOrder) {
            return json_encode([
                'ok' => true,
                'created' => false,
                'order_id' => $existingOrder->id,
                'status' => $existingOrder->status,
                'message' => 'Order already exists for this request.',
            ], JSON_UNESCAPED_SLASHES);
        }

        $status = trim((string) ($request['status'] ?? 'booked'));
        $allowedStatuses = ['booked', 'fulfillment_in_progress'];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'booked';
        }

        /** @var Order $order */
        $order = DB::transaction(function () use ($quote, $status): Model {
            $quote->forceFill([
                'status' => 'accepted',
            ])->save();

            $this->serviceRequest->quotes()
                ->whereKeyNot($quote->id)
                ->where('status', 'pending')
                ->update(['status' => 'rejected']);

            $order = Order::query()->create([
                'request_id' => $this->serviceRequest->id,
                'quote_id' => $quote->id,
                'buyer_id' => $this->actor->id,
                'seller_profile_id' => $quote->profile_id,
                'status' => $status,
            ]);

            $this->serviceRequest->forceFill([
                'status' => $status,
            ])->save();

            $this->thread->messages()->create([
                'sender_id' => null,
                'type' => 'system',
                'tag' => 'order_created',
                'body' => "Order #{$order->id} was created from Quote #{$quote->id}.",
                'attachments' => null,
                'meta' => [
                    'source' => 'tool',
                    'tool' => self::class,
                    'order_id' => $order->id,
                    'quote_id' => $quote->id,
                ],
            ]);

            return $order;
        });

        return json_encode([
            'ok' => true,
            'created' => true,
            'order_id' => $order->id,
            'quote_id' => $quote->id,
            'status' => $order->status,
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'quote_id' => $schema->integer()->required(),
            'status' => $schema->string()->enum(['booked', 'fulfillment_in_progress']),
        ];
    }

    protected function encodeError(string $message): string
    {
        return json_encode([
            'ok' => false,
            'error' => $message,
        ], JSON_UNESCAPED_SLASHES);
    }
}
