<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user && method_exists($user, 'currentAccessToken')) {
            $user->currentAccessToken()?->delete();
        }

        if ($user && method_exists($user, 'token')) {
            $token = $user->token();

            if ($token && method_exists($token, 'revoke')) {
                $token->revoke();
            }
        }

        return response()->json(status: 204);
    }
}
