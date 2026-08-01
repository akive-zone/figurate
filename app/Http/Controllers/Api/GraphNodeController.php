<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Graph\StoreGraphNodeRequest;
use App\Http\Requests\Server\Graph\UpdateGraphNodeRequest;
use App\Models\Server\User;
use App\Support\Graph\GraphMutationService;
use App\Support\Graph\GraphNodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GraphNodeController extends Controller
{
    public function __construct(
        protected GraphNodeService $graphNodes,
        protected GraphMutationService $graphMutations,
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
        $node = $this->graphMutations->createNode($actor, $validated);

        return response()->json([
            'data' => $this->graphNodes->map($node, $actor),
        ], 201);
    }

    public function update(UpdateGraphNodeRequest $request, string $type, string $node): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $attributes = $request->validated('attributes');
        abort_unless(is_array($attributes), 422);

        $updatedNode = $this->graphMutations->updateNode($actor, $type, $node, $attributes);

        return response()->json([
            'data' => $this->graphNodes->map($updatedNode, $actor),
        ]);
    }

    public function destroy(Request $request, string $type, string $node): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->graphMutations->deleteNode($actor, $type, $node);

        return response()->json(status: 204);
    }
}
