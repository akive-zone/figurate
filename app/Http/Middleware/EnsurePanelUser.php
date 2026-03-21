<?php

namespace App\Http\Middleware;

use App\Models\Server\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePanelUser
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isGadget()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Gadget users do not have access to panel mode.',
                ], 403);
            }

            return redirect()
                ->route('chat.index')
                ->with('error', 'Gadget users do not have access to panel mode.');
        }

        return $next($request);
    }
}
