<?php

namespace Figurate\AccountManager\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server\User;
use Figurate\AccountManager\Support\AccountContextFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class CurrentAccountController extends Controller
{
    public function __construct(protected AccountContextFactory $accountContextFactory) {}

    public function show(Request $request): JsonResponse
    {
        $user = $this->resolveAuthenticatedUser($request);

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Authentication is required.',
            ], 401);
        }

        if (! $user->canActAsHuman()) {
            return response()->json([
                'message' => 'A subject account is required.',
            ], 403);
        }

        $account = $this->accountContextFactory->forUser($user)->primaryAccount();

        if ($account === null) {
            return response()->json([
                'message' => 'No primary account is available for this user.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $account->id,
                'uuid' => $account->uuid,
                'name' => $account->name,
                'status' => $account->status,
            ],
        ]);
    }

    protected function resolveAuthenticatedUser(Request $request): ?User
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $user;
        }

        $tokenValue = $request->bearerToken();

        if (! is_string($tokenValue) || trim($tokenValue) === '') {
            return null;
        }

        $token = PersonalAccessToken::findToken($tokenValue);

        if (! $token instanceof PersonalAccessToken) {
            return null;
        }

        $tokenable = $token->tokenable;

        if (! $tokenable instanceof User) {
            return null;
        }

        $tokenable->withAccessToken($token);

        return $tokenable;
    }
}
