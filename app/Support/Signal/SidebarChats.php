<?php

namespace App\Support\Signal;

use App\Models\Server\Channel;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SidebarChats
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function forRequest(Request $request): array
    {
        return $this->forUser($request->user());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forUser(?User $actor): array
    {
        if (! $actor) {
            return [];
        }

        return $this->queryVisibleChannels($actor)
            ->get()
            ->map(fn (Channel $channel): array => $this->mapChannelListItem($channel))
            ->values()
            ->all();
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function cursorPageForRequest(Request $request): array
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
                ->map(fn (Channel $channel): array => $this->mapChannelListItem($channel))
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

        if ($actor->type !== 'system') {
            $channelsQuery->whereHas('actorStates', function ($stateQuery) use ($actor): void {
                $stateQuery
                    ->where('actor_type', $actor->getMorphClass())
                    ->where('actor_id', $actor->id)
                    ->where('status', 'active');
            });
        }

        return $channelsQuery;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapChannelListItem(Channel $channel): array
    {
        $threadCollection = $channel->threads()
            ->orderBy('created_at')
            ->get();

        $threads = $threadCollection
            ->map(function ($thread): array {
                return [
                    'id' => $thread->uuid,
                    'title' => $thread->title ?: 'Thread',
                    'purpose' => $thread->purpose,
                    'status' => $thread->status,
                    'created_at' => optional($thread->created_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $threadIds = $threadCollection->pluck('id')->filter()->values();
        $latestMessageModel = null;
        if ($threadIds->isNotEmpty()) {
            $latestMessageModel = Message::query()
                ->where('messageable_type', (new Thread)->getMorphClass())
                ->whereIn('messageable_id', $threadIds->all())
                ->latest('created_at')
                ->first();
        }

        $latestMessage = null;
        if ($latestMessageModel instanceof Message) {
            $latestMessage = [
                'id' => $latestMessageModel->id,
                'body' => $latestMessageModel->body,
                'created_at' => optional($latestMessageModel->created_at)?->toIso8601String(),
                'sender_name' => null,
            ];
        }

        return [
            'id' => $channel->uuid,
            'status' => $channel->status ?? 'open',
            'last_message_at' => $latestMessage['created_at'] ?? optional($channel->created_at)?->toIso8601String(),
            'threads' => $threads,
            'latest_message' => $latestMessage,
        ];
    }
}
