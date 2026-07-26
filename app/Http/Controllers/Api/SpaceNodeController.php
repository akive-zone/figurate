<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Graph\ListGraphNodesRequest;
use App\Models\Server\Space;
use App\Models\Server\User;
use App\Support\Graph\GraphNodeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

class SpaceNodeController extends Controller
{
    public function __construct(protected GraphNodeService $graphNodes) {}

    public function __invoke(ListGraphNodesRequest $request, string $space): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $parent = $this->graphNodes->resolve($actor, 'space', $space);
        abort_unless($parent instanceof Space, 404);
        $page = $this->graphNodes->paginateChildren(
            $actor,
            $parent,
            $request->validated('cursor'),
            (int) ($request->validated('per_page') ?? 25),
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
