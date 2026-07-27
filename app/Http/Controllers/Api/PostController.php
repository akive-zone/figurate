<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Post\StorePostRequest;
use App\Http\Resources\Server\Api\PostResource;
use App\Models\Server\Post;
use App\Models\Server\User;
use App\Support\Graph\GraphNodeService;
use App\Support\Graph\NodeFormer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PostController extends Controller
{
    public function store(
        StorePostRequest $request,
        NodeFormer $nodeFormer,
        GraphNodeService $graphNodes,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $result = $nodeFormer->form($actor, [
            'type' => 'post',
            ...$request->validated(),
        ]);

        return response()->json([
            'data' => $graphNodes->map($result['node'], $actor),
            'relations' => $result['relations'],
        ], 201);
    }

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
