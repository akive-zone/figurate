<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server\Channel;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class ConversationPostController extends Controller
{
    public function index(string $conversation): JsonResponse
    {
        $channelRecord = Channel::query()
            ->where('uuid', $conversation)
            ->firstOrFail();

        Gate::authorize('view', $channelRecord);

        $channelPosts = $channelRecord->conversationPosts()
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
            ->filter(fn (array $item): bool => is_string($item['created_at'] ?? null))
            ->all();

        $threadHistory = $channelRecord->conversationThreads()
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
            ->filter(fn (array $item): bool => is_string($item['created_at'] ?? null))
            ->all();

        $posts = collect(array_merge($channelPosts, $threadHistory))
            ->sortBy('created_at')
            ->values()
            ->all();

        return response()->json([
            'data' => $posts,
            'channel' => [
                'id' => $channelRecord->uuid,
                'status' => $channelRecord->status,
            ],
        ]);
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

        return $actor->actorable_type !== User::class;
    }
}
