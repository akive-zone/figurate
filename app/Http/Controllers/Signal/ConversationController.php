<?php

namespace App\Http\Controllers\Signal;

use App\Http\Controllers\Controller;
use App\Models\Server\Conversation;
use App\Models\Server\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->where(function ($query) use ($user): void {
                $query->where('requester_id', $user->id)
                    ->orWhereHas('profile', function ($profileQuery) use ($user): void {
                        $profileQuery->where('user_id', $user->id);
                    });
            })
            ->with([
                'profile:id,display_name,user_id',
                'request:id,title,status',
                'latestMessage:id,conversation_id,sender_id,body,created_at',
                'latestMessage.sender:id,name',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Conversation $conversation): array {
                return [
                    'id' => $conversation->id,
                    'status' => $conversation->status,
                    'last_message_at' => optional($conversation->last_message_at)?->toIso8601String(),
                    'request' => $conversation->request ? [
                        'id' => $conversation->request->id,
                        'title' => $conversation->request->title,
                        'status' => $conversation->request->status,
                    ] : null,
                    'profile' => $conversation->profile ? [
                        'id' => $conversation->profile->id,
                        'display_name' => $conversation->profile->display_name,
                    ] : null,
                    'latest_message' => $conversation->latestMessage ? [
                        'id' => $conversation->latestMessage->id,
                        'body' => $conversation->latestMessage->body,
                        'created_at' => $conversation->latestMessage->created_at->toIso8601String(),
                        'sender_name' => $conversation->latestMessage->sender?->name,
                    ] : null,
                ];
            })
            ->values();

        return Inertia::render('Signal/Conversations/Index', [
            'conversations' => $conversations,
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $conversation->load([
            'profile:id,display_name,user_id',
            'request:id,title,description,status,requester_id',
            'request.quotes:id,request_id,profile_id,amount,currency,details,status,created_at',
            'request.order:id,request_id,quote_id,status',
            'messages' => fn ($query) => $query
                ->with(['sender:id,name'])
                ->latest('id')
                ->limit(100),
        ]);

        $currentUser = $request->user();
        $serviceRequest = $conversation->request;
        $isRequester = $serviceRequest?->requester_id === $currentUser->id;
        $requestStatus = $serviceRequest?->status;

        $pendingQuotes = $serviceRequest?->quotes
            ?->where('status', 'pending')
            ->values()
            ?? collect();

        return Inertia::render('Signal/Conversations/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
                'profile' => $conversation->profile ? [
                    'id' => $conversation->profile->id,
                    'display_name' => $conversation->profile->display_name,
                ] : null,
                'request' => $conversation->request ? [
                    'id' => $conversation->request->id,
                    'title' => $conversation->request->title,
                    'description' => $conversation->request->description,
                    'status' => $conversation->request->status,
                    'order' => $conversation->request->order ? [
                        'id' => $conversation->request->order->id,
                        'status' => $conversation->request->order->status,
                    ] : null,
                    'quotes' => $conversation->request->quotes
                        ->map(function (Quote $quote): array {
                            return [
                                'id' => $quote->id,
                                'amount' => $quote->amount,
                                'currency' => $quote->currency,
                                'details' => $quote->details,
                                'status' => $quote->status,
                                'created_at' => $quote->created_at->toIso8601String(),
                            ];
                        })
                        ->values(),
                ] : null,
                'messages' => $conversation->messages
                    ->sortBy('id')
                    ->map(function ($message) {
                        return [
                            'id' => $message->id,
                            'sender_id' => $message->sender_id,
                            'sender_name' => $message->sender?->name,
                            'body' => $message->body,
                            'created_at' => $message->created_at->toIso8601String(),
                        ];
                    })
                    ->values(),
                'actions' => [
                    'can_accept_quote' => $isRequester && $requestStatus === 'quoted' && $pendingQuotes->isNotEmpty() && ! $serviceRequest?->order,
                ],
            ],
        ]);
    }
}
