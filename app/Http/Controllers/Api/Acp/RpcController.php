<?php

namespace App\Http\Controllers\Api\Acp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Acp\HandleAcpRpcRequest;
use App\Models\Server\User;
use App\Support\Acp\AcpMethodRouter;
use Illuminate\Http\JsonResponse;

class RpcController extends Controller
{
    public function __invoke(HandleAcpRpcRequest $request, AcpMethodRouter $methodRouter): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        $response = $methodRouter->handle(
            actor: $actor,
            method: (string) $payload['method'],
            params: is_array($payload['params'] ?? null) ? $payload['params'] : [],
        );

        if (! $request->exists('id')) {
            return response()->json(null, 204);
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $payload['id'] ?? null,
            ...$response,
        ]);
    }
}
