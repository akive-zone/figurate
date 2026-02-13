<?php

namespace App\Http\Controllers\Signal;

use App\Http\Controllers\Controller;
use App\Models\Server\AgentConversationMessage;
use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Message;
use App\Models\Server\Quote;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
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
                'requests',
                'requests.latestMessage',
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
            'requests',
        ]);

        $serviceRequest = $channel->requests->first();

        if ($serviceRequest) {
            $serviceRequest->load([
                'threads:id,threadable_type,threadable_id,purpose,title,phase,status,created_at',
                'threads.actors:id,thread_id,actorable_type,actorable_id,role,status,priority',
                'threads.actorMemories:id,thread_id,thread_actor_id,conversation_id,last_used_at',
                'threads.actorMemories.threadActor:id,thread_id,actorable_type,actorable_id,role,status,priority',
            ]);
        }

        $currentUser = $request->user();
        $isRequester = $serviceRequest?->hasUserActor($currentUser, ServiceRequest::ActionAsker) ?? false;
        $requestStatus = $serviceRequest?->status;

        $quotes = $serviceRequest ? $serviceRequest->quotes()->latest('id')->get() : collect();
        $currentOrder = $serviceRequest?->currentOrder();

        $pendingQuotes = $quotes
            ?->where('status', 'pending')
            ->values()
            ?? collect();

        $threads = $serviceRequest?->threads
            ?->sortByDesc('created_at')
            ->values()
            ?? collect();

        $actorStateThreadId = ChannelActorState::query()
            ->where('channel_id', $channel->id)
            ->where('actor_type', $currentUser->getMorphClass())
            ->where('actor_id', $currentUser->getKey())
            ->value('thread_id');
        $queryThreadId = $request->integer('thread');

        $activeThread = $threads->firstWhere('id', $queryThreadId)
            ?? $threads->firstWhere('id', $actorStateThreadId)
            ?? $threads->firstWhere('purpose', Thread::PurposeMain)
            ?? $threads->first();

        if ($activeThread && $queryThreadId) {
            ChannelActorState::query()->updateOrCreate(
                [
                    'channel_id' => $channel->id,
                    'actor_type' => $currentUser->getMorphClass(),
                    'actor_id' => $currentUser->getKey(),
                ],
                [
                    'thread_id' => $activeThread->id,
                    'status' => ChannelActorState::StatusActive,
                ],
            );
        }

        $agentMessages = collect();
        $threadMessages = collect();

        $activeHandlerActor = $activeThread?->actors
            ?->where('role', ThreadActor::RoleHandler)
            ->where('status', ThreadActor::StatusActive)
            ->sortBy('priority')
            ->first();
        $activeHandlerMemory = $activeThread?->actorMemories
            ->firstWhere('thread_actor_id', $activeHandlerActor?->id);

        if (filled($activeHandlerMemory?->conversation_id)) {
            $agentMessages = AgentConversationMessage::query()
                ->where('conversation_id', $activeHandlerMemory->conversation_id)
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
                    'order' => $currentOrder ? [
                        'id' => $currentOrder->id,
                        'status' => $currentOrder->status,
                    ] : null,
                    'quotes' => $quotes
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
                    $handlerActor = $thread->actors
                        ->where('role', ThreadActor::RoleHandler)
                        ->where('status', ThreadActor::StatusActive)
                        ->sortBy('priority')
                        ->first();
                    $handlerMemory = $thread->actorMemories
                        ->firstWhere('thread_actor_id', $handlerActor?->id);

                    return [
                        'id' => $thread->id,
                        'purpose' => $thread->purpose,
                        'title' => $thread->title,
                        'phase' => $thread->phase,
                        'handler_actor' => $handlerActor?->actorName(),
                        'status' => $thread->status,
                        'has_ai_history' => filled($handlerMemory?->conversation_id),
                    ];
                })->values(),
                'active_thread' => $activeThread?->id,
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
                        $attachments = $message->attachments;

                        if (! is_array($attachments) || $attachments === []) {
                            $attachments = $message->getMedia('attachments')
                                ->map(fn ($media): array => [
                                    'id' => $media->id,
                                    'name' => $media->name ?: $media->file_name,
                                    'file_name' => $media->file_name,
                                    'mime' => $media->mime_type,
                                    'size' => $media->size,
                                    'url' => $media->getUrl(),
                                    'path' => $media->getUrl(),
                                ])
                                ->values()
                                ->all();
                        }

                        return [
                            'id' => $message->id,
                            'sender_name' => $message->sender?->name,
                            'content' => $message->body,
                            'attachments' => $attachments,
                            'created_at' => $message->created_at->toIso8601String(),
                        ];
                    })
                    ->values(),
                'actions' => [
                    'can_create_thread' => $isRequester,
                    'can_prompt_agent' => $isRequester && $activeThread !== null,
                    'can_accept_quote' => $isRequester && $requestStatus === 'quoted' && $pendingQuotes->isNotEmpty() && ! $currentOrder,
                ],
            ],
        ]);
    }
}
