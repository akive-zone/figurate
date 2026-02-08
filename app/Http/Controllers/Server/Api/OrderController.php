<?php

namespace App\Http\Controllers\Server\Api;

use App\Http\Controllers\Controller;
use App\Models\Server\Channel;
use App\Models\Server\Order;
use App\Models\Server\Quote;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\ThreadActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function acceptQuote(Channel $channel, Quote $quote): JsonResponse
    {
        Gate::authorize('view', $channel);
        Gate::authorize('view', $quote);
        Gate::authorize('update', $quote);
        Gate::authorize('create', Order::class);

        $currentUser = request()->user();
        $serviceRequest = $channel->requests()->first();
        $profile = $channel->profile;

        if (! $serviceRequest || ! $profile || ! $serviceRequest->hasUserActor($currentUser, ServiceRequest::ActionAsker)) {
            abort(403);
        }

        if ($quote->request_id !== $serviceRequest->id || $quote->profile_id !== $profile->id) {
            abort(404);
        }

        if ($serviceRequest->order()->exists()) {
            abort(422, 'Order already created for this request.');
        }

        DB::transaction(function () use ($channel, $currentUser, $profile, $quote, $serviceRequest): void {
            $quote->forceFill([
                'status' => 'accepted',
            ])->save();

            $serviceRequest->quotes()
                ->whereKeyNot($quote->id)
                ->where('status', 'pending')
                ->update(['status' => 'rejected']);

            $buyer = $serviceRequest->primaryRequester();

            if (! $buyer) {
                abort(422, 'Request has no asker actor.');
            }

            Order::query()->create([
                'request_id' => $serviceRequest->id,
                'quote_id' => $quote->id,
                'buyer_id' => $buyer->id,
                'seller_profile_id' => $profile->id,
                'status' => 'booked',
            ]);

            $serviceRequest->forceFill([
                'status' => 'booked',
            ])->save();

            $channel->forceFill([
                'status' => 'booked',
                'last_message_at' => now(),
            ])->save();

            if (! $serviceRequest->threads()->whereHas('actors', function ($query): void {
                $query->where('role', ThreadActor::RolePrimaryHandler)
                    ->where('actorable_type', ThreadActor::ActorOrderAgent)
                    ->whereNull('actorable_id')
                    ->where('status', ThreadActor::StatusActive);
            })->exists()) {
                $orderThread = $serviceRequest->threads()->create([
                    'created_by' => $currentUser->id,
                    'title' => 'Order Fulfillment',
                    'phase' => 'order_kickoff',
                    'status' => 'open',
                ]);

                $orderThread->actors()->create([
                    'actorable_type' => ThreadActor::ActorOrderAgent,
                    'actorable_id' => null,
                    'role' => ThreadActor::RolePrimaryHandler,
                    'status' => ThreadActor::StatusActive,
                    'priority' => 1,
                    'config' => null,
                ]);
            }

            $serviceRequest->messages()->create([
                'sender_id' => $currentUser->id,
                'type' => 'text',
                'body' => 'Quote accepted. Order is now booked and fulfillment has started.',
                'attachments' => null,
                'meta' => null,
            ]);
        });

        return response()->json([
            'message' => 'Quote accepted. Fulfillment started.',
            'channel_id' => $channel->id,
            'request_id' => $serviceRequest->id,
        ]);
    }
}
