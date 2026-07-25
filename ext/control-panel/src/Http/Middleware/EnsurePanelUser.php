<?php

namespace Figurate\ControlPanel\Http\Middleware;

use App\Models\Server\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePanelUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isWidget()) {
            abort(403, 'You do not have access to panel mode.');
        }

        return $next($request);
    }
}
