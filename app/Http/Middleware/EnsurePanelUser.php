<?php

namespace App\Http\Middleware;

use App\Contracts\Accounts\AccountContextFactory;
use App\Models\Server\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePanelUser
{
    public function __construct(protected AccountContextFactory $accountContextFactory) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isGadget() && ! $this->accountContextFactory->forUser($user)->hasAccount()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Anonymous gadget users do not have access to panel mode.',
                ], 403);
            }

            return redirect()
                ->route('chat.index')
                ->with('error', 'Anonymous gadget users do not have access to panel mode.');
        }

        return $next($request);
    }
}
