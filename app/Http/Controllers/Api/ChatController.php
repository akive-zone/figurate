<?php

namespace App\Http\Controllers\Api;

use App\Features\Actions\Chat\ProjectAgentTurns;
use App\Features\Actions\Chat\ProjectMessageExtra;
use App\Features\Operations\Chat\SubmitChatMessageOperation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Chat\StoreChatRequest;
use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class ChatController extends Controller
{
    public function __construct(protected ProjectMessageExtra $projectMessageExtra) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->cursorPageForRequest($request));
    }

    public function show(
        Request $request,
        string $chat,
        ProjectAgentTurns $projectAgentTurns,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        [$threadRecord, $channelRecord] = $this->resolveThreadForChat($chat, $actor);

        if (! $threadRecord) {
            return response()->json([
                'data' => [],
                'turns' => [],
                'chat' => [
                    'id' => $chat,
                    'channel_id' => $channelRecord?->uuid,
                    'thread_id' => null,
                ],
                'thread' => null,
            ]);
        }

        $threadMessages = $threadRecord->messages()
            ->orderBy('created_at')
            ->get();

        $messages = $threadMessages
            ->map(function (Message $message) use ($threadRecord): array {
                return [
                    'kind' => 'message',
                    'scope' => 'thread',
                    'thread_id' => $threadRecord->uuid,
                    'id' => $message->id,
                    'sender_name' => null,
                    'source' => data_get($message->meta, 'source'),
                    'is_agent' => data_get($message->meta, 'source') === 'agent_response',
                    'content' => $this->messageContent($message),
                    'extra' => $this->projectMessageExtra->execute($message),
                    'created_at' => optional($message->created_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $turns = $projectAgentTurns->execute($threadRecord, $threadMessages, $actor);

        return response()->json([
            'data' => $messages,
            'turns' => $turns,
            'chat' => [
                'id' => $chat,
                'channel_id' => $channelRecord?->uuid,
                'thread_id' => $threadRecord->uuid,
            ],
            'thread' => [
                'id' => $threadRecord->uuid,
                'purpose' => $threadRecord->purpose,
                'phase' => $threadRecord->phase,
                'status' => $threadRecord->status,
            ],
        ]);
    }

    public function showMessageTurns(
        Request $request,
        string $chat,
        Message $message,
        ProjectAgentTurns $projectAgentTurns
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        [$threadRecord] = $this->resolveThreadForChat($chat, $actor);

        if (! $threadRecord) {
            abort(404, 'Thread not found.');
        }

        if (
            $message->messageable_type !== $threadRecord->getMorphClass()
            || $message->messageable_id !== $threadRecord->getKey()
        ) {
            abort(404, 'Message not found in this thread.');
        }

        $threadMessages = $threadRecord->messages()
            ->orderBy('created_at')
            ->get();
        $turns = collect($projectAgentTurns->execute($threadRecord, $threadMessages, $actor))
            ->filter(fn (array $turn): bool => (int) ($turn['prompt_message_id'] ?? 0) === (int) $message->id)
            ->values()
            ->all();

        return response()->json([
            'data' => $turns,
            'thread' => $threadRecord->uuid,
            'message_id' => $message->id,
        ]);
    }

    public function store(
        StoreChatRequest $request,
        SubmitChatMessageOperation $submitChatMessageOperation,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $attachments = $request->file('content.attachments', []);

        $result = $submitChatMessageOperation->run([
            'actor' => $actor,
            'channel' => $validated['channel'] ?? null,
            'thread' => $validated['thread'] ?? null,
            'content' => is_array($validated['content'] ?? null) ? $validated['content'] : [],
            'extra' => is_array($validated['extra'] ?? null) ? $validated['extra'] : [],
            'attachments' => is_array($attachments) ? $attachments : [$attachments],
            'idempotency_key' => $request->header('X-Idempotency-Key'),
        ]);

        return response()->json($result['body'], $result['status']);
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    protected function cursorPageForRequest(Request $request): array
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return [
                'data' => [],
                'meta' => [
                    'next_cursor' => null,
                    'prev_cursor' => null,
                    'per_page' => 20,
                ],
            ];
        }

        $perPage = max(5, min(100, (int) $request->integer('per_page', 20)));
        $paginator = $this->queryVisibleChannels($actor)
            ->cursorPaginate($perPage, ['*'], 'cursor', $request->query('cursor'));

        return [
            'data' => collect($paginator->items())
                ->map(fn (Channel $channel): array => $this->mapChatListItem($channel, $actor))
                ->values()
                ->all(),
            'meta' => [
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'per_page' => $perPage,
            ],
        ];
    }

    protected function queryVisibleChannels(User $actor): Builder
    {
        Gate::forUser($actor)->authorize('viewAny', Channel::class);

        $channelsQuery = Channel::query()->latest('created_at');

        $channelsQuery->whereHas('actorStates', function ($stateQuery) use ($actor): void {
            $stateQuery
                ->where('actorable_type', $actor->getMorphClass())
                ->where('actorable_id', $actor->id)
                ->where('status', ChannelActorState::StatusActive);
        });

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
                'extra' => $this->projectMessageExtra->execute($latestMessageModel),
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
        return [
            'id' => $thread->uuid,
            'title' => $thread->title ?: 'Thread',
            'purpose' => $thread->purpose,
            'status' => $thread->status,
            'created_at' => optional($thread->created_at)?->toIso8601String(),
            'last_message_at' => $this->formatIso8601($thread->messages_max_created_at),
            'is_active_for_actor' => is_int($actorState?->thread_id) && $actorState->thread_id === $thread->id,
        ];
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
     * @return array{0: ?Thread, 1: ?Channel}
     */
    protected function resolveThreadForChat(string $chat, User $actor): array
    {
        $threadRecord = Thread::query()
            ->where('uuid', $chat)
            ->first();

        if ($threadRecord instanceof Thread) {
            Gate::forUser($actor)->authorize('view', $threadRecord);

            $channelRecord = null;
            if ($threadRecord->threadable instanceof Channel) {
                $channelRecord = $threadRecord->threadable;
                Gate::forUser($actor)->authorize('view', $channelRecord);
            }

            return [$threadRecord, $channelRecord];
        }

        $channelRecord = Channel::query()
            ->where('uuid', $chat)
            ->firstOrFail();

        Gate::forUser($actor)->authorize('view', $channelRecord);

        $threadIds = $channelRecord->conversationThreadIds();

        if ($threadIds->isEmpty()) {
            return [null, $channelRecord];
        }

        $actorStateThreadId = $channelRecord->actorStates()
            ->where('actorable_type', $actor->getMorphClass())
            ->where('actorable_id', $actor->id)
            ->where('status', ChannelActorState::StatusActive)
            ->value('thread_id');

        if (is_int($actorStateThreadId) && $actorStateThreadId > 0 && $threadIds->contains($actorStateThreadId)) {
            $activeThread = Thread::query()
                ->whereKey($actorStateThreadId)
                ->first();

            if ($activeThread instanceof Thread) {
                Gate::forUser($actor)->authorize('view', $activeThread);

                return [$activeThread, $channelRecord];
            }
        }

        $latestThread = Thread::query()
            ->whereIn('id', $threadIds->all())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($latestThread instanceof Thread) {
            Gate::forUser($actor)->authorize('view', $latestThread);
        }

        return [$latestThread, $channelRecord];
    }
}
