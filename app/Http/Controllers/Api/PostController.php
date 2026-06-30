<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Server\Api\PostResource;
use App\Models\Server\Post;
use App\Models\Server\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PostController extends Controller
{
    public function show(Request $request, string $post): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $postRecord = $this->resolvePost($post);

        Gate::forUser($actor)->authorize('view', $postRecord);

        return response()->json([
            'data' => PostResource::make($postRecord),
        ]);
    }

    protected function resolvePost(string $post): Post
    {
        return Post::query()
            ->where('ulid', $post)
            ->when(ctype_digit($post), fn ($query) => $query->orWhereKey((int) $post))
            ->firstOrFail();
    }
}
