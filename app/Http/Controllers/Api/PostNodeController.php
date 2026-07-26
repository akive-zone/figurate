<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server\Post;
use App\Models\Server\User;
use App\Support\Graph\GraphNodeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostNodeController extends Controller
{
    public function __construct(protected GraphNodeService $graphNodes) {}

    public function __invoke(Request $request, string $post): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $parent = $this->graphNodes->resolve($actor, 'post', $post);
        abort_unless($parent instanceof Post, 404);
        $children = $this->graphNodes->children($actor, $parent);

        return response()->json([
            'data' => $children
                ->map(fn (Model $node): array => $this->graphNodes->map($node, $actor))
                ->all(),
            'parent' => $this->graphNodes->reference($parent),
            'meta' => [
                'count' => $children->count(),
            ],
        ]);
    }
}
