<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Post\InvokePostRequest;
use App\Models\Server\Post;
use App\Models\Server\User;
use App\Support\Orchestrate\PostInvocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PostInvocationController extends Controller
{
    public function store(
        InvokePostRequest $request,
        PostInvocationService $postInvocations,
        string $post,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $sourcePost = $this->resolvePost($post);

        Gate::forUser($actor)->authorize('view', $sourcePost);

        return response()->json([
            'data' => $postInvocations->invoke(
                actor: $actor,
                sourcePost: $sourcePost,
                instructions: (string) $request->validated('instructions'),
            ),
        ], 202);
    }

    protected function resolvePost(string $post): Post
    {
        return Post::query()
            ->where('ulid', $post)
            ->when(ctype_digit($post), fn ($query) => $query->orWhereKey((int) $post))
            ->firstOrFail();
    }
}
