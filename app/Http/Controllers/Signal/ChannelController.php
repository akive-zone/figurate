<?php

namespace App\Http\Controllers\Signal;

use App\Http\Controllers\Controller;
use App\Models\Server\AgentConversationMessage;
use App\Models\Server\Channel;
use App\Models\Server\Message;
use App\Models\Server\Quote;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $channels = Channel::query()
            ->where(function ($query) use ($user): void {
                $query->where('requester_id', $user->id)
                    ->orWhereHas('profile', function ($profileQuery) use ($user): void {
                        $profileQuery->where('user_id', $user->id);
                    });
            })
            ->with([
                'profile:id,display_name,user_id',
                'requests:id,title,status',
                'requests.latestMessage:id,messageable_type,messageable_id,sender_id,body,created_at',
                'requests.latestMessage.sender:id,name',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Channel $channel): array {
                $serviceRequest = $channel->requests->first();
                $latestMessage = $serviceRequest?->latestMessage;

                return [
                    'id' => $channel->id,
                    'status' => $channel->status,
                    'last_message_at' => optional($channel->last_message_at)?->toIso8601String(),
                    'request' => $serviceRequest ? [
                        'id' => $serviceRequest->id,
                        'title' => $serviceRequest->title,
                        'status' => $serviceRequest->status,
                    ] : null,
                    'profile' => $channel->profile ? [
                        'id' => $channel->profile->id,
                        'display_name' => $channel->profile->display_name,
                    ] : null,
                    'latest_message' => $latestMessage ? [
                        'id' => $latestMessage->id,
                        'body' => $latestMessage->body,
                        'created_at' => $latestMessage->created_at->toIso8601String(),
                        'sender_name' => $latestMessage->sender?->name,
                    ] : null,
                ];
            })
            ->values();

        return Inertia::render('Signal/Channels/Index', [
            'channels' => $channels,
        ]);
    }

    public function show(Request $request, Channel $channel): Response
    {
        Gate::authorize('view', $channel);

        $channel->load([
            'profile:id,display_name,user_id',
            'requests:id,title,description,status',
        ]);

        $serviceRequest = $channel->requests->first();

        if ($serviceRequest) {
            $serviceRequest->load([
                'quotes:id,request_id,profile_id,amount,currency,details,status,created_at',
                'order:id,request_id,quote_id,status',
                'threads:id,threadable_type,threadable_id,title,phase,agent_key,ai_conversation_id,status,created_at',
            ]);
        }

        $currentUser = $request->user();
        $isRequester = $serviceRequest?->hasUserActor($currentUser, ServiceRequest::ActionAsker) ?? false;
        $requestStatus = $serviceRequest?->status;

        $pendingQuotes = $serviceRequest?->quotes
            ?->where('status', 'pending')
            ->values()
            ?? collect();

        $threads = $serviceRequest?->threads
            ?->sortByDesc('created_at')
            ->values()
            ?? collect();

        $activeThread = $threads->firstWhere('id', (int) $request->integer('thread'))
            ?? $threads->first();

        $agentMessages = collect();
        $threadMessages = collect();

        if ($activeThread?->ai_conversation_id) {
            $agentMessages = AgentConversationMessage::query()
                ->where('conversation_id', $activeThread->ai_conversation_id)
                ->with('user:id,name')
                ->orderBy('created_at')
                ->limit(100)
                ->get();
        }

        if ($activeThread) {
            $threadMessages = $activeThread->messages()
                ->with('sender:id,name')
                ->orderBy('created_at')
                ->limit(100)
                ->get();
        }

        return Inertia::render('Signal/Channels/Show', [
            'channel' => [
                'id' => $channel->id,
                'status' => $channel->status,
                'profile' => $channel->profile ? [
                    'id' => $channel->profile->id,
                    'display_name' => $channel->profile->display_name,
                ] : null,
                'request' => $serviceRequest ? [
                    'id' => $serviceRequest->id,
                    'title' => $serviceRequest->title,
                    'description' => $serviceRequest->description,
                    'status' => $serviceRequest->status,
                    'order' => $serviceRequest->order ? [
                        'id' => $serviceRequest->order->id,
                        'status' => $serviceRequest->order->status,
                    ] : null,
                    'quotes' => $serviceRequest->quotes
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
                'threads' => $threads->map(function (Thread $thread): array {
                    return [
                        'id' => $thread->id,
                        'title' => $thread->title,
                        'phase' => $thread->phase,
                        'agent_key' => $thread->agent_key,
                        'status' => $thread->status,
                        'has_ai_history' => filled($thread->ai_conversation_id),
                    ];
                })->values(),
                'active_thread_id' => $activeThread?->id,
                'agent_messages' => $agentMessages
                    ->map(function (AgentConversationMessage $message): array {
                        return [
                            'id' => $message->id,
                            'role' => $message->role,
                            'agent' => $message->agent,
                            'sender_name' => $message->user?->name,
                            'content' => $message->content,
                            'created_at' => $message->created_at->toIso8601String(),
                        ];
                    })
                    ->values(),
                'thread_messages' => $threadMessages
                    ->map(function (Message $message): array {
                        return [
                            'id' => $message->id,
                            'sender_name' => $message->sender?->name,
                            'content' => $message->body,
                            'attachments' => $message->attachments ?? [],
                            'created_at' => $message->created_at->toIso8601String(),
                        ];
                    })
                    ->values(),
                'actions' => [
                    'can_create_thread' => $isRequester,
                    'can_prompt_agent' => $isRequester && $activeThread !== null,
                    'can_accept_quote' => $isRequester && $requestStatus === 'quoted' && $pendingQuotes->isNotEmpty() && ! $serviceRequest?->order,
                ],
            ],
        ]);
    }
}
