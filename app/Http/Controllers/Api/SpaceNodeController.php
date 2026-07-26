<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server\Space;
use App\Models\Server\User;
use App\Support\Graph\GraphNodeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpaceNodeController extends Controller
{
    public function __construct(protected GraphNodeService $graphNodes) {}

    public function __invoke(Request $request, string $space): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $parent = $this->graphNodes->resolve($actor, 'space', $space);
        abort_unless($parent instanceof Space, 404);
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
