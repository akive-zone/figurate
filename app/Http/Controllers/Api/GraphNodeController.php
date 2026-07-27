<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Graph\StoreGraphNodeRequest;
use App\Http\Requests\Server\Graph\UpdateGraphNodeRequest;
use App\Models\Server\User;
use App\Support\Graph\GraphNodeService;
use App\Support\Graph\NodeFormer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GraphNodeController extends Controller
{
    public function __construct(
        protected GraphNodeService $graphNodes,
        protected NodeFormer $nodeFormer,
    ) {}

    public function show(Request $request, string $type, string $node): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $resolvedNode = $this->graphNodes->resolve($actor, $type, $node);

        return response()->json([
            'data' => $this->graphNodes->map($resolvedNode, $actor),
        ]);
    }

    public function store(StoreGraphNodeRequest $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $result = $this->nodeFormer->form($actor, $validated);

        return response()->json([
            'data' => $this->graphNodes->map($result['node'], $actor),
        ], 201);
    }

    public function update(UpdateGraphNodeRequest $request, string $type, string $node): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $attributes = $request->validated('attributes');
        abort_unless(is_array($attributes), 422);

        $result = $this->nodeFormer->form($actor, [
            'type' => $type,
            'id' => $node,
            'attributes' => $attributes,
        ]);

        return response()->json([
            'data' => $this->graphNodes->map($result['node'], $actor),
        ]);
    }

    public function destroy(Request $request, string $type, string $node): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $resolvedNode = $this->graphNodes->resolve($actor, $type, $node);
        Gate::forUser($actor)->authorize('delete', $resolvedNode);

        abort_if(
            $this->graphNodes->children($actor, $resolvedNode)->isNotEmpty(),
            409,
            'A node with child nodes cannot be deleted.',
        );

        DB::transaction(function () use ($resolvedNode): void {
            if (method_exists($resolvedNode, 'relations')) {
                $resolvedNode->relations()->delete();
            }

            foreach (['inboundSpaceRelations', 'inboundThreadRelations', 'inboundPostRelations'] as $method) {
                if (method_exists($resolvedNode, $method)) {
                    $resolvedNode->{$method}()->each->delete();
                }
            }

            $resolvedNode->delete();
        });

        return response()->json(status: 204);
    }
}
