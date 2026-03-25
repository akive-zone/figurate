<?php

namespace App\Http\Controllers\Api;

use App\Features\Actions\Conversation\ProjectAgentTurns;
use App\Features\Actions\Conversation\ProjectMessageExtra;
use App\Features\Actions\Conversation\ResolveConversationRouteThread;
use App\Features\Operations\Chat\SubmitChatMessageOperation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Chat\StoreChatRequest;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function __construct(
        protected ProjectMessageExtra $projectMessageExtra,
        protected ResolveConversationRouteThread $resolveConversationRouteThread,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->cursorPageForRequest($request));
    }

    public function show(
        Request $request,
        string $conversation,
        ProjectAgentTurns $projectAgentTurns,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        [$threadRecord, $spaceRecord] = $this->resolveConversationRouteThread->execute($conversation, $actor);

        if (! $threadRecord) {
            return response()->json([
                'data' => [],
                'turns' => [],
                'conversation' => [
                    'id' => $conversation,
                    'space_id' => $spaceRecord?->uuid,
                    'thread_id' => null,
                ],
                'thread' => null,
            ]);
        }

        $threadMessages = $threadRecord->messages()
            ->orderBy('created_at')
            ->get();

        $messages = $threadMessages
            ->map(function (Post $message) use ($threadRecord): array {
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
            'conversation' => [
                'id' => $conversation,
                'space_id' => $spaceRecord?->uuid,
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
            'space' => $validated['space'] ?? null,
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
        $paginator = $this->queryVisibleSpaces($actor)
            ->cursorPaginate($perPage, ['*'], 'cursor', $request->query('cursor'));

        return [
            'data' => collect($paginator->items())
                ->map(fn (Space $space): array => $this->mapConversationListItem($space, $actor))
                ->values()
                ->all(),
            'meta' => [
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'per_page' => $perPage,
            ],
        ];
    }

    protected function queryVisibleSpaces(User $actor): Builder
    {
        Gate::forUser($actor)->authorize('viewAny', Space::class);

        $spacesQuery = Space::query()->latest('created_at');

        $spacesQuery->whereHas('actorStates', function ($stateQuery) use ($actor): void {
            $stateQuery
                ->whereMorphedTo('actor', $actor)
                ->where('status', SpaceActorState::StatusActive);
        });

        return $spacesQuery;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapConversationListItem(Space $space, User $actor): array
    {
        $actorState = $this->actorStateForSpace($space, $actor);
        $threadsPaginator = $this->recentThreadsQuery($space, $actorState)
            ->cursorPaginate(5, ['*'], 'threads_cursor', null);
        $threads = collect($threadsPaginator->items());
        $latestMessageModel = $space->latestConversationMessage();
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
            'id' => $space->uuid,
            'name' => $this->inferConversationName($space, $threads, $latestMessageModel?->text),
            'space' => [
                'id' => $space->uuid,
                'status' => $space->status ?? 'open',
                'active_thread_id' => $activeThreadUuid,
                'last_message_at' => $latestMessage['created_at'] ?? optional($space->created_at)?->toIso8601String(),
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
    protected function inferConversationName(
        Space $space,
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

        return sprintf('Conversation %s', mb_substr($space->uuid, 0, 8));
    }

    protected function actorStateForSpace(Space $space, User $actor): ?SpaceActorState
    {
        return $space->actorStates()
            ->whereMorphedTo('actor', $actor)
            ->where('status', SpaceActorState::StatusActive)
            ->latest('updated_at')
            ->first();
    }

    protected function recentThreadsQuery(Space $space, ?SpaceActorState $actorState): Builder
    {
        $threadIds = $space->conversationThreadIds();
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
    protected function mapThreadListItem(Thread $thread, ?SpaceActorState $actorState): array
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
    protected function messageContent(Post $message): array
    {
        return [
            'text' => is_string($message->text) ? $message->text : '',
            'attachments' => is_array($message->attachments) ? $message->attachments : [],
            'actions' => is_array($message->actions) ? $message->actions : [],
            'errors' => is_array($message->errors) ? $message->errors : [],
        ];
    }
}
