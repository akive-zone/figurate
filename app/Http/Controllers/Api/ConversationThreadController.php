<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConversationThreadController extends Controller
{
    public function index(Request $request, string $conversation): JsonResponse
    {
        $channel = Channel::query()
            ->where('uuid', $conversation)
            ->firstOrFail();

        Gate::authorize('view', $channel);

        return response()->json($this->cursorPageForRequest($request, $channel));
    }

    public function store(Request $request, string $conversation): JsonResponse
    {
        $channel = Channel::query()
            ->where('uuid', $conversation)
            ->firstOrFail();

        Gate::authorize('update', $channel);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:50'],
            'nature' => ['nullable', 'string', 'in:agent,human,mixed'],
        ]);

        $thread = $channel->threads()->create([
            'title' => $data['title'],
            'purpose' => $data['purpose'] ?? Thread::PurposeExecution,
            'phase' => $this->defaultPhase($data['purpose'] ?? Thread::PurposeExecution),
            'status' => 'open',
        ]);

        $actor = $request->user();
        if ($actor) {
            $thread->actors()->create([
                'actorable_type' => $actor->getMorphClass(),
                'actorable_id' => $actor->id,
                'role' => 'member',
                'status' => 'active',
            ]);
        }

        return response()->json([
            'data' => $this->mapThreadListItem($thread, null),
        ], 201);
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    protected function cursorPageForRequest(Request $request, Channel $channel): array
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return [
                'data' => [],
                'meta' => [
                    'next_cursor' => null,
                    'prev_cursor' => null,
                    'per_page' => 5,
                ],
            ];
        }

        Gate::forUser($actor)->authorize('view', $channel);

        $perPage = max(5, min(50, (int) $request->integer('per_page', 5)));
        $actorState = $this->actorStateForChannel($channel, $actor);
        $paginator = $this->recentThreadsQuery($channel, $actorState)
            ->cursorPaginate($perPage, ['*'], 'cursor', $request->query('cursor'));

        return [
            'data' => collect($paginator->items())
                ->map(fn (Thread $thread): array => $this->mapThreadListItem($thread, $actorState))
                ->values()
                ->all(),
            'meta' => [
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'per_page' => $perPage,
            ],
        ];
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

    protected function defaultPhase(string $purpose): string
    {
        return match ($purpose) {
            Thread::PurposePlanning => 'scope_planning',
            Thread::PurposeExecution => 'order_kickoff',
            Thread::PurposeBilling => 'billing_review',
            Thread::PurposeDispute => 'opened',
            Thread::PurposeSupport => 'support_open',
            Thread::PurposeSystem => 'system_open',
            default => 'request_intake',
        };
    }
}
