<?php

namespace App\Http\Controllers\Signal;

use App\Http\Controllers\Controller;
use App\Models\Server\Conversation;
use App\Models\Server\ConversationMessage;
use App\Models\Server\Order;
use App\Models\Server\Quote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ConversationFulfillmentController extends Controller
{
    public function acceptQuote(Conversation $conversation, Quote $quote): RedirectResponse
    {
        Gate::authorize('view', $conversation);
        Gate::authorize('view', $quote);
        Gate::authorize('update', $quote);
        Gate::authorize('create', Order::class);

        $currentUser = request()->user();
        $serviceRequest = $conversation->request;
        $profile = $conversation->profile;

        if (! $serviceRequest || ! $profile || $serviceRequest->requester_id !== $currentUser->id) {
            abort(403);
        }

        if ($quote->request_id !== $serviceRequest->id || $quote->profile_id !== $profile->id) {
            abort(404);
        }

        if ($serviceRequest->order()->exists()) {
            return redirect()
                ->route('signal.chat.show', $conversation)
                ->with('error', 'Order already created for this request.');
        }

        DB::transaction(function () use ($conversation, $currentUser, $profile, $quote, $serviceRequest): void {
            $quote->forceFill([
                'status' => 'accepted',
            ])->save();

            $serviceRequest->quotes()
                ->whereKeyNot($quote->id)
                ->where('status', 'pending')
                ->update(['status' => 'rejected']);

            Order::query()->create([
                'request_id' => $serviceRequest->id,
                'quote_id' => $quote->id,
                'buyer_id' => $serviceRequest->requester_id,
                'seller_profile_id' => $profile->id,
                'status' => 'booked',
            ]);

            $serviceRequest->forceFill([
                'status' => 'booked',
            ])->save();

            $conversation->forceFill([
                'status' => 'booked',
                'last_message_at' => now(),
            ])->save();

            ConversationMessage::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $currentUser->id,
                'type' => 'text',
                'body' => 'Quote accepted. Order is now booked and fulfillment has started.',
                'attachments' => null,
                'meta' => null,
            ]);
        });

        return redirect()
            ->route('signal.chat.show', $conversation)
            ->with('success', 'Quote accepted. Fulfillment started.');
    }
}
