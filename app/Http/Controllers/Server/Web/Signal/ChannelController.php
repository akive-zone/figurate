<?php

namespace App\Http\Controllers\Server\Web\Signal;

use App\Http\Controllers\Controller;
use App\Models\Server\Channel;
use App\Models\Server\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Signal/Requests/Create');
    }

    public function index(Request $request): Response
    {
        try {
            $channels = $this->queryVisibleChannels($request)->map(function (Channel $channel): array {
                $serviceRequest = $channel->requests()->latest('id')->first();
                $latestMessage = null;

                if ($serviceRequest) {
                    $latest = $serviceRequest->messages()->latest('created_at')->first();

                    if ($latest) {
                        $latestMessage = [
                            'id' => $latest->id,
                            'body' => $latest->body,
                            'created_at' => optional($latest->created_at)?->toIso8601String(),
                            'sender_name' => null,
                        ];
                    }
                }

                return [
                    'id' => $channel->uuid,
                    'status' => $channel->status ?? 'open',
                    'last_message_at' => $latestMessage['created_at'] ?? optional($channel->created_at)?->toIso8601String(),
                    'request' => $serviceRequest ? [
                        'id' => $serviceRequest->id,
                        'title' => $serviceRequest->title,
                        'status' => $serviceRequest->status,
                    ] : null,
                    'latest_message' => $latestMessage,
                ];
            })->values()->all();
        } catch (\Throwable) {
            $channels = [];
        }

        return Inertia::render('Signal/Channels/Index', [
            'channels' => $channels,
        ]);
    }

    public function show(Request $request, string $channel): Response
    {
        try {
            $channelRecord = Channel::query()
                ->where('uuid', $channel)
                ->first();

            if (! $channelRecord || ! Gate::forUser($request->user())->allows('view', $channelRecord)) {
                $channelPayload = null;
            } else {
                $serviceRequest = $channelRecord->requests()->latest('id')->first();
                $threadMessages = [];

                if ($serviceRequest) {
                    $threadMessages = $serviceRequest->messages()
                        ->orderBy('created_at')
                        ->get()
                        ->map(function (Message $message): array {
                            return [
                                'id' => $message->id,
                                'sender_name' => null,
                                'content' => $message->body,
                                'attachments' => is_array($message->attachments) ? $message->attachments : [],
                                'created_at' => optional($message->created_at)?->toIso8601String(),
                            ];
                        })
                        ->values()
                        ->all();
                }

                $channelPayload = [
                    'id' => $channelRecord->uuid,
                    'status' => $channelRecord->status ?? 'open',
                    'request' => $serviceRequest ? [
                        'id' => $serviceRequest->id,
                        'title' => $serviceRequest->title,
                        'description' => $serviceRequest->description,
                        'status' => $serviceRequest->status,
                        'quotes' => [],
                    ] : null,
                    'threads' => [],
                    'active_thread' => is_string($request->query('thread')) ? (string) $request->query('thread') : null,
                    'agent_messages' => [],
                    'thread_messages' => $threadMessages,
                    'actions' => [
                        'can_create_thread' => false,
                        'can_prompt_agent' => true,
                        'can_accept_quote' => false,
                    ],
                ];
            }
        } catch (\Throwable) {
            $channelPayload = null;
        }

        return Inertia::render('Signal/Channels/Show', [
            'channel' => $channelPayload,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Channel>
     */
    protected function queryVisibleChannels(Request $request): \Illuminate\Support\Collection
    {
        $actor = $request->user();
        if (! $actor) {
            return collect();
        }

        Gate::forUser($actor)->authorize('viewAny', Channel::class);

        $channelsQuery = Channel::query()->latest('created_at');

        if ($actor->type !== 'system') {
            $channelsQuery->whereHas('requests', function ($query) use ($actor): void {
                $query->where(function ($participantQuery) use ($actor): void {
                    $participantQuery
                        ->whereHas('users', function ($userQuery) use ($actor): void {
                            $userQuery->whereKey($actor->id);
                        })
                        ->orWhereHas('profiles', function ($profileQuery) use ($actor): void {
                            $profileQuery->where('profiles.user_id', $actor->id);
                        });
                });
            });
        }

        return $channelsQuery->get();
    }
}
