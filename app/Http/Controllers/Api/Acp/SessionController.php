<?php

namespace App\Http\Controllers\Api\Acp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Acp\PromptAcpSessionRequest;
use App\Http\Requests\Server\Acp\StoreAcpSessionRequest;
use App\Models\Server\User;
use App\Support\Acp\AcpSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function index(Request $request, AcpSessionService $acpSessionService): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => $acpSessionService->listSessions($actor),
        ]);
    }

    public function store(StoreAcpSessionRequest $request, AcpSessionService $acpSessionService): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => $acpSessionService->createSession(
                actor: $actor,
                spaceUuid: $request->validated('space_uuid'),
                title: $request->validated('title'),
                purpose: $request->validated('purpose'),
                phase: $request->validated('phase'),
            ),
        ], 201);
    }

    public function show(Request $request, AcpSessionService $acpSessionService, string $session): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => $acpSessionService->loadSession($actor, $session),
        ]);
    }

    public function prompt(
        PromptAcpSessionRequest $request,
        AcpSessionService $acpSessionService,
        string $session
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        return response()->json([
            'data' => $acpSessionService->promptSession(
                actor: $actor,
                sessionUuid: $session,
                spaceUuid: $request->validated('space_uuid'),
                text: $request->validated('text'),
            ),
        ], 202);
    }
}
