<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Graph\ListGraphNodesRequest;
use App\Models\Server\Post;
use App\Models\Server\User;
use App\Support\Graph\GraphNodeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

class PostNodeController extends Controller
{
    public function __construct(protected GraphNodeService $graphNodes) {}

    public function __invoke(ListGraphNodesRequest $request, string $post): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $parent = $this->graphNodes->resolve($actor, 'post', $post);
        abort_unless($parent instanceof Post, 404);
        $page = $this->graphNodes->paginateChildren(
            $actor,
            $parent,
            $request->validated('cursor'),
            (int) ($request->validated('per_page') ?? 25),
            $request->validated('type'),
        );
        $children = $page['nodes'];

        return response()->json([
            'data' => $children
                ->map(fn (Model $node): array => $this->graphNodes->map($node, $actor))
                ->all(),
            'parent' => $this->graphNodes->reference($parent),
            'meta' => $page['meta'],
        ]);
    }
}
