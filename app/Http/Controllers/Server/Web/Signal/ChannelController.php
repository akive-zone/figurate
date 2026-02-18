<?php

namespace App\Http\Controllers\Server\Web\Signal;

use App\Http\Controllers\Controller;
use App\Models\Server\Channel;
use App\Models\Server\Message;
use App\Support\Signal\SidebarChats;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    public function __construct(private SidebarChats $sidebarChats) {}

    public function create(Request $request): Response
    {
        return Inertia::render('Signal/Requests/Create', [
            'channels' => $this->safeChannelsPayload($request),
        ]);
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Signal/Channels/Index', [
            'channels' => $this->safeChannelsPayload($request),
        ]);
    }

    public function show(Request $request, string $channel): Response
    {
        return $this->renderChannel($request, $channel, null);
    }

    public function showThread(Request $request, string $channel, string $thread): Response
    {
        return $this->renderChannel($request, $channel, $thread);
    }

    protected function renderChannel(Request $request, string $channel, ?string $requestedThread): Response
    {
        $channels = $this->safeChannelsPayload($request);

        try {
            $channelRecord = Channel::query()
                ->where('uuid', $channel)
                ->first();

            if (! $channelRecord) {
                $channelPayload = null;
            } else {
                $channelRecord->load([
                    'posts',
                ]);

                $threads = $channelRecord->threads()
                    ->orderBy('created_at')
                    ->get();
                $threadsPayload = $threads
                    ->map(function ($thread): array {
                        return [
                            'id' => $thread->uuid,
                            'title' => $thread->title ?: 'Thread',
                            'purpose' => $thread->purpose,
                            'status' => $thread->status,
                            'created_at' => optional($thread->created_at)?->toIso8601String(),
                        ];
                    })
                    ->all();
                $activeThread = $requestedThread;
                $knownThreadIds = collect($threadsPayload)->pluck('id');
                if ($activeThread !== null && ! $knownThreadIds->contains($activeThread)) {
                    $activeThread = null;
                }
                $channelFeed = [];

                $threadMessages = [];
                if ($activeThread !== null) {
                    $activeThreadRecord = $threads->firstWhere('uuid', $activeThread);

                    if ($activeThreadRecord) {
                        $threadMessages = $activeThreadRecord->messages()
                            ->orderBy('created_at')
                            ->get()
                            ->map(function (Message $message) use ($activeThread): array {
                                return [
                                    'kind' => 'message',
                                    'scope' => 'thread',
                                    'thread_id' => $activeThread,
                                    'id' => $message->id,
                                    'sender_name' => null,
                                    'content' => $message->body,
                                    'attachments' => is_array($message->attachments) ? $message->attachments : [],
                                    'created_at' => optional($message->created_at)?->toIso8601String(),
                                ];
                            })
                            ->all();
                    }
                }

                $channelPosts = $channelRecord->posts
                    ->sortBy('occurred_at')
                    ->values()
                    ->map(function ($post): array {
                        $content = data_get($post->payload, 'title')
                            ?? data_get($post->payload, 'description')
                            ?? $post->type
                            ?? 'Channel update';

                        return [
                            'kind' => 'post',
                            'scope' => 'channel',
                            'thread_id' => null,
                            'id' => $post->id,
                            'sender_name' => null,
                            'content' => $content,
                            'attachments' => [],
                            'created_at' => optional($post->occurred_at ?? $post->created_at)?->toIso8601String(),
                        ];
                    })
                    ->all();

                $channelFeed = collect(array_merge($channelFeed, $channelPosts))
                    ->filter(fn (array $item): bool => is_string($item['created_at'] ?? null))
                    ->sortBy('created_at')
                    ->values()
                    ->all();

                $threadMessages = collect($threadMessages)
                    ->filter(fn (array $item): bool => is_string($item['created_at'] ?? null))
                    ->sortBy('created_at')
                    ->values()
                    ->all();

                $channelPayload = [
                    'id' => $channelRecord->uuid,
                    'status' => $channelRecord->status ?? 'open',
                    'threads' => $threadsPayload,
                    'active_thread' => $activeThread,
                    'channel_feed' => $channelFeed,
                    'thread_messages' => $threadMessages,
                ];
            }
        } catch (\Throwable) {
            $channelPayload = null;
        }

        return Inertia::render('Signal/Channels/Show', [
            'channels' => $channels,
            'channel' => $channelPayload,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function safeChannelsPayload(Request $request): array
    {
        try {
            return $this->sidebarChats->forRequest($request);
        } catch (\Throwable) {
            return [];
        }
    }
}
