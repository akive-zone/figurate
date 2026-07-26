<?php

namespace App\Http\Middleware;

use App\Models\Server\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireApiAbility
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();

        if (
            ! $user instanceof User
            || $user->currentAccessToken() === null
            || (! $user->tokenCan('*') && ! $user->tokenCan($ability))
        ) {
            return response()->json([
                'message' => 'The API credential does not have the required ability.',
                'required_ability' => $ability,
            ], 403);
        }

        return $next($request);
    }
}
