<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Post\StorePostRequest;
use App\Http\Resources\Server\Api\PostResource;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ThreadPostController extends Controller
{
    public function store(StorePostRequest $request, string $thread): JsonResponse
    {
        $threadRecord = Thread::query()
            ->where('uuid', $thread)
            ->firstOrFail();

        Gate::authorize('update', $threadRecord);

        $validated = $request->validated();
        $post = $threadRecord->posts()->create([
            'type' => $validated['type'] ?? Post::TypeMessage,
            'tag' => $validated['tag'] ?? null,
            'status' => $validated['status'] ?? Post::StatusActive,
            'payload' => $request->postPayload(),
            'meta' => $request->postMeta(),
            'occurred_at' => $validated['occurred_at'] ?? now(),
        ]);

        $actor = $request->user();
        if ($actor instanceof User) {
            $post->attachRelation($actor, Post::RelationRoleSender);
        }

        return response()->json([
            'data' => PostResource::make($post),
        ], 201);
    }
}
