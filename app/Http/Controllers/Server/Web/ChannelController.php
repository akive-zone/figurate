<?php

namespace App\Http\Controllers\Server\Web;

use App\Http\Controllers\Controller;
use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Channels/Index', [
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

        $channelRecord = Channel::query()
            ->where('uuid', $channel)
            ->first();

        if (! $channelRecord) {
            throw (new ModelNotFoundException)->setModel(Channel::class, [$channel]);
        }

        $threads = $channelRecord->conversationThreads();
        $threadsPayload = $threads
            ->map(function ($thread): array {
                $actors = $thread->actors;
                $nature = $this->resolveThreadNature($actors);

                return [
                    'id' => $thread->uuid,
                    'title' => $thread->title ?: 'Thread',
                    'purpose' => $thread->purpose,
                    'nature' => $nature,
                    'status' => $thread->status,
                    'actors' => $actors->map(fn ($actor) => [
                        'role' => $actor->role,
                        'name' => $actor->actorReference(),
                        'is_agent' => $this->isAgentActor($actor),
                    ])->all(),
                    'created_at' => optional($thread->created_at)?->toIso8601String(),
                ];
            })
            ->all();

        $activeThread = $requestedThread;
        $knownThreadIds = collect($threadsPayload)->pluck('id');
        if ($activeThread !== null && ! $knownThreadIds->contains($activeThread)) {
            $activeThread = null;
        }

        // Identify suggested open threads for the workspace (e.g., latest agent and latest human)
        $latestAgentThread = collect($threadsPayload)->first(fn ($t) => in_array($t['nature'], ['agent', 'mixed']));
        $latestHumanThread = collect($threadsPayload)->first(fn ($t) => in_array($t['nature'], ['human', 'mixed']));

        $suggestedOpenThreads = collect([$latestAgentThread, $latestHumanThread])
            ->filter()
            ->pluck('id')
            ->unique()
            ->values();

        // If no nature-specific threads found, suggest the latest thread as a fallback
        if ($suggestedOpenThreads->isEmpty() && ! empty($threadsPayload)) {
            $suggestedOpenThreads->push(collect($threadsPayload)->first()['id']);
        }

        $suggestedOpenThreads = $suggestedOpenThreads->all();

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
                            'source' => data_get($message->meta, 'source'),
                            'is_agent' => data_get($message->meta, 'source') === 'agent_response',
                            'content' => $this->messageContent($message),
                            'extra' => $this->messageExtra($message),
                            'created_at' => optional($message->created_at)?->toIso8601String(),
                        ];
                    })
                    ->all();
            }
        }

        $channelPosts = $channelRecord->conversationPosts()
            ->values()
            ->map(function ($post): array {
                $content = data_get($post->payload, 'title')
                    ?? data_get($post->payload, 'description')
                    ?? $post->type
                    ?? 'Channel update';

                return [
                    'kind' => 'post',
                    'scope' => 'channel',
                    'type' => $post->type,
                    'status' => $post->status,
                    'thread_id' => null,
                    'id' => $post->id,
                    'sender_name' => null,
                    'content' => $content,
                    'attachments' => [],
                    'created_at' => optional($post->occurred_at ?? $post->created_at)?->toIso8601String(),
                ];
            })
            ->all();

        $threadHistory = $threads
            ->map(function ($thread): array {
                return [
                    'kind' => 'thread_event',
                    'scope' => 'channel',
                    'id' => $thread->uuid,
                    'title' => $thread->title ?: 'Thread started',
                    'nature' => $this->resolveThreadNature($thread->actors),
                    'content' => sprintf('Started a new %s conversation: **%s**', $this->resolveThreadNature($thread->actors), $thread->title ?: 'New Thread'),
                    'created_at' => optional($thread->created_at)?->toIso8601String(),
                ];
            })
            ->all();

        $channelFeed = collect(array_merge($channelFeed, $channelPosts, $threadHistory))
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
            'suggested_open_threads' => $suggestedOpenThreads,
            'channel_feed' => $channelFeed,
            'thread_messages' => $threadMessages,
        ];

        return Inertia::render('Channels/Show', [
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
            return $this->channelsForRequest($request);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function channelsForRequest(Request $request): array
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return [];
        }

        return $this->queryVisibleChannels($actor)
            ->get()
            ->map(fn (Channel $channel): array => $this->mapChatListItem($channel, $actor))
            ->values()
            ->all();
    }

    protected function queryVisibleChannels(User $actor): Builder
    {
        Gate::forUser($actor)->authorize('viewAny', Channel::class);

        $channelsQuery = Channel::query()->latest('created_at');

        if ($actor->type !== 'system') {
            $channelsQuery->whereHas('actorStates', function ($stateQuery) use ($actor): void {
                $stateQuery
                    ->where('actorable_type', $actor->getMorphClass())
                    ->where('actorable_id', $actor->id)
                    ->where('status', ChannelActorState::StatusActive);
            });
        }

        return $channelsQuery;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapChatListItem(Channel $channel, User $actor): array
    {
        $actorState = $this->actorStateForChannel($channel, $actor);
        $threadsPaginator = $this->recentThreadsQuery($channel, $actorState)
            ->cursorPaginate(5, ['*'], 'threads_cursor', null);
        $threads = collect($threadsPaginator->items());
        $latestMessageModel = $channel->latestConversationMessage();
        $activeThreadUuid = null;

        if (is_int($actorState?->thread_id) && $actorState->thread_id > 0) {
            $activeThreadUuid = Thread::query()
                ->whereKey($actorState->thread_id)
                ->value('uuid');
        }

        $latestMessage = null;
        if ($latestMessageModel) {
            $latestMessage = [
                'id' => $latestMessageModel->id,
                'content' => $this->messageContent($latestMessageModel),
                'extra' => $this->messageExtra($latestMessageModel),
                'created_at' => optional($latestMessageModel->created_at)?->toIso8601String(),
                'sender_name' => null,
            ];
        }

        return [
            'id' => $channel->uuid,
            'name' => $this->inferChatName($channel, $threads, $latestMessageModel?->text),
            'channel' => [
                'id' => $channel->uuid,
                'status' => $channel->status ?? 'open',
                'active_thread_id' => $activeThreadUuid,
                'last_message_at' => $latestMessage['created_at'] ?? optional($channel->created_at)?->toIso8601String(),
                'latest_message' => $latestMessage,
            ],
            'threads' => $threads
                ->map(fn (Thread $thread): array => $this->mapThreadListItem($thread, $actorState))
                ->values()
                ->all(),
            'threads_meta' => [
                'next_cursor' => $threadsPaginator->nextCursor()?->encode(),
                'prev_cursor' => $threadsPaginator->previousCursor()?->encode(),
                'per_page' => 5,
            ],
        ];
    }

    /**
     * @param  Collection<int, Thread>  $threads
     */
    protected function inferChatName(
        Channel $channel,
        Collection $threads,
        ?string $latestMessageBody
    ): string {
        $threadTitle = trim((string) ($threads->first()?->title ?? ''));
        if ($threadTitle !== '') {
            return $threadTitle;
        }

        $messagePreview = trim((string) ($latestMessageBody ?? ''));
        if ($messagePreview !== '') {
            return mb_substr($messagePreview, 0, 60);
        }

        return sprintf('Chat %s', mb_substr($channel->uuid, 0, 8));
    }

    protected function actorStateForChannel(Channel $channel, User $actor): ?ChannelActorState
    {
        return $channel->actorStates()
            ->where('actorable_type', $actor->getMorphClass())
            ->where('actorable_id', $actor->id)
            ->where('status', ChannelActorState::StatusActive)
            ->latest('updated_at')
            ->first();
    }

    protected function recentThreadsQuery(Channel $channel, ?ChannelActorState $actorState): Builder
    {
        $threadIds = $channel->conversationThreadIds();
        $query = Thread::query()
            ->whereIn('id', $threadIds->all())
            ->withMax('messages', 'created_at');

        if (is_int($actorState?->thread_id) && $actorState->thread_id > 0) {
            $query->orderByRaw('case when id = ? then 0 else 1 end', [$actorState->thread_id]);
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapThreadListItem(Thread $thread, ?ChannelActorState $actorState): array
    {
        $actors = $thread->actors;
        $nature = $this->resolveThreadNature($actors);

        return [
            'id' => $thread->uuid,
            'title' => $thread->title ?: 'Thread',
            'purpose' => $thread->purpose,
            'nature' => $nature,
            'status' => $thread->status,
            'created_at' => optional($thread->created_at)?->toIso8601String(),
            'last_message_at' => $this->formatIso8601($thread->messages_max_created_at),
            'is_active_for_actor' => is_int($actorState?->thread_id) && $actorState->thread_id === $thread->id,
        ];
    }

    protected function resolveThreadNature(Collection $actors): string
    {
        $hasAgent = false;
        $hasHuman = false;

        foreach ($actors as $actor) {
            if ($this->isAgentActor($actor)) {
                $hasAgent = true;
            } else {
                $hasHuman = true;
            }
        }

        if ($hasAgent && $hasHuman) {
            return 'mixed';
        }

        if ($hasAgent) {
            return 'agent';
        }

        return 'human';
    }

    protected function isAgentActor(ThreadActor $actor): bool
    {
        if ($actor->actorable_id === null) {
            return true;
        }

        // Logic to determine if a morphable is an agent
        // Assuming App\Models\Server\User is human, others (like AI Agents) are agents.
        return $actor->actorable_type !== User::class;
    }

    protected function formatIso8601(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return null;
    }

    protected function trimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<string, mixed>
     */
    protected function messageContent(Message $message): array
    {
        return [
            'text' => is_string($message->text) ? $message->text : '',
            'attachments' => is_array($message->attachments) ? $message->attachments : [],
            'actions' => is_array($message->actions) ? $message->actions : [],
            'errors' => is_array($message->errors) ? $message->errors : [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function messageExtra(Message $message): ?array
    {
        $surface = data_get($message->meta, 'a2ui');
        $surface = is_array($surface) ? $surface : null;
        $dataModel = $this->trimmedString(data_get($message->meta, 'a2ui_client_data_model'));
        $capabilities = data_get($message->meta, 'a2ui_client_capabilities');
        $capabilities = is_array($capabilities) ? $capabilities : null;

        if ($surface === null && $dataModel === null && $capabilities === null) {
            return null;
        }

        return [
            'a2ui' => [
                'surface' => $surface,
                'config' => [
                    'a2uiClientDataModel' => $dataModel,
                    'a2uiClientCapabilities' => $capabilities,
                ],
            ],
        ];
    }
}
