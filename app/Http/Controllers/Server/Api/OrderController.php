<?php

namespace App\Http\Controllers\Server\Api;

use App\Http\Controllers\Controller;
use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Order;
use App\Models\Server\Quote;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
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

        if ($serviceRequest->hasOrder()) {
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

            $order = Order::query()->create([
                'type' => 'order.booked',
                'status' => 'booked',
                'payload' => [
                    'buyer_id' => $buyer->id,
                    'seller_profile_id' => $profile->id,
                ],
                'meta' => [
                    'source' => 'api.order.accept_quote',
                ],
                'occurred_at' => now(),
            ]);

            $order->attachRelation($channel, 'primary');
            $order->attachRelation($serviceRequest, 'request');
            $order->attachRelation($quote, 'quote');
            $order->attachRelation($buyer, 'buyer');
            $order->attachRelation($profile, 'seller_profile');

            $serviceRequest->forceFill([
                'status' => 'booked',
            ])->save();

            $channel->forceFill([
                'status' => 'booked',
                'last_message_at' => now(),
            ])->save();

            $orderThread = $serviceRequest->threads()
                ->where('purpose', Thread::PurposeExecution)
                ->where('status', 'open')
                ->whereHas('actors', function ($query): void {
                    $query->where('role', ThreadActor::RoleHandler)
                        ->where('actorable_type', ThreadActor::ActorOrderAgent)
                        ->whereNull('actorable_id')
                        ->where('status', ThreadActor::StatusActive);
                })
                ->latest('id')
                ->first();

            if (! $orderThread) {
                $orderThread = $serviceRequest->threads()->create([
                    'purpose' => Thread::PurposeExecution,
                    'title' => 'Order Fulfillment',
                    'phase' => 'order_kickoff',
                    'status' => 'open',
                ]);

                $orderThread->actors()->create([
                    'actorable_type' => ThreadActor::ActorOrderAgent,
                    'actorable_id' => null,
                    'role' => ThreadActor::RoleHandler,
                    'status' => ThreadActor::StatusActive,
                    'priority' => 1,
                    'config' => null,
                ]);
            }

            ChannelActorState::query()->updateOrCreate(
                [
                    'channel_id' => $channel->id,
                    'actor_type' => $currentUser->getMorphClass(),
                    'actor_id' => $currentUser->getKey(),
                ],
                [
                    'thread_id' => $orderThread->id,
                    'status' => ChannelActorState::StatusActive,
                ],
            );

            $serviceRequest->messages()->create([
                'senderable_type' => $currentUser->getMorphClass(),
                'senderable_id' => $currentUser->getKey(),
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
