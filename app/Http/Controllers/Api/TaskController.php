<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server\User;
use App\Support\Orchestrate\PostInvocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function show(
        Request $request,
        PostInvocationService $postInvocations,
        string $task,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => $postInvocations->task($actor, $task),
        ]);
    }
}
