<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'message' => 'Authentication is required.',
            ], 401);
        }

        foreach ($abilities as $ability) {
            $ability = trim($ability);

            if ($ability === '') {
                continue;
            }

            if (! is_object($user) || ! method_exists($user, 'tokenCan') || ! $user->tokenCan($ability)) {
                return response()->json([
                    'message' => 'This token is not authorized for the requested ability.',
                    'required_ability' => $ability,
                ], 403);
            }
        }

        return $next($request);
    }
}
