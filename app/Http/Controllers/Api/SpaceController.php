<?php

namespace App\Http\Controllers\Api;

use App\Features\Actions\Chat\ProjectMessageExtra;
use App\Features\Actions\Chat\ResolveNodeInvocation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Space\StoreSpaceRequest;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SpaceController extends Controller
{
    public function __construct(
        protected ProjectMessageExtra $projectMessageExtra,
        protected ResolveNodeInvocation $resolveNodeInvocation,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->cursorPageForRequest($request));
    }

    public function show(Request $request, string $space): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $spaceRecord = Space::query()
            ->where('uuid', $space)
            ->firstOrFail();

        Gate::forUser($actor)->authorize('view', $spaceRecord);

        $actorState = $this->actorStateForSpace($spaceRecord, $actor);
        $activeThreadUuid = null;

        if (is_int($actorState?->thread_id) && $actorState->thread_id > 0) {
            $activeThreadUuid = Thread::query()
                ->whereKey($actorState->thread_id)
                ->value('uuid');
        }

        return response()->json([
            'data' => [
                'id' => $spaceRecord->uuid,
                'status' => $spaceRecord->status,
                'active_thread_id' => $activeThreadUuid,
                'invocation' => $this->resolveNodeInvocation->execute($actor, $spaceRecord),
                'created_at' => optional($spaceRecord->created_at)?->toIso8601String(),
            ],
        ]);
    }

    public function store(StoreSpaceRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        Gate::forUser($actor)->authorize('create', Space::class);

        $space = DB::transaction(function () use ($actor, $request): Space {
            $space = Space::query()->create([
                'status' => $request->validated('status') ?? 'open',
            ]);

            SpaceActorState::query()->create([
                'space_id' => $space->id,
                'thread_id' => null,
                'actorable_type' => $actor->getMorphClass(),
                'actorable_id' => $actor->getKey(),
                'status' => SpaceActorState::StatusActive,
            ]);

            return $space;
        });

        return response()->json([
            'data' => [
                'id' => $space->uuid,
                'status' => $space->status,
                'active_thread_id' => null,
                'created_at' => optional($space->created_at)?->toIso8601String(),
            ],
        ], 201);
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
                ->map(fn (Space $space): array => $this->mapSpaceListItem($space, $actor))
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
    protected function mapSpaceListItem(Space $space, User $actor): array
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
                'id' => $latestMessageModel->ulid,
                'content' => $this->messageContent($latestMessageModel),
                'extra' => $this->projectMessageExtra->execute($latestMessageModel),
                'created_at' => optional($latestMessageModel->created_at)?->toIso8601String(),
                'sender_name' => null,
            ];
        }

        return [
            'id' => $space->uuid,
            'name' => $this->inferSpaceName($space, $threads, $latestMessageModel?->text),
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
    protected function inferSpaceName(Space $space, Collection $threads, ?string $latestMessageBody): string
    {
        $threadTitle = trim((string) ($threads->first()?->title ?? ''));
        if ($threadTitle !== '') {
            return $threadTitle;
        }

        $messagePreview = trim((string) ($latestMessageBody ?? ''));
        if ($messagePreview !== '') {
            return mb_substr($messagePreview, 0, 60);
        }

        return sprintf('Space %s', mb_substr($space->uuid, 0, 8));
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
