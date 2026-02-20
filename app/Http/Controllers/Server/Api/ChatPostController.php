<?php

namespace App\Http\Controllers\Server\Api;

use App\Http\Controllers\Controller;
use App\Models\Server\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ChatPostController extends Controller
{
    public function index(string $chat): JsonResponse
    {
        $channelRecord = Channel::query()
            ->where('uuid', $chat)
            ->firstOrFail();

        Gate::authorize('view', $channelRecord);

        $posts = $channelRecord->conversationPosts()
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
}
