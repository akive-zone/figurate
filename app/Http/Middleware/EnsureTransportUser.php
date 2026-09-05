<?php

namespace App\Http\Middleware;

use App\Models\Server\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTransportUser
{
    public function handle(Request $request, Closure $next, string ...$allowedTypes): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->deny($request, 401, 'Authentication is required.');
        }

        $configuredAllowedTypes = config('auth.interactive_user_types', [User::TypeSubject, User::TypeWidget]);
        $resolvedAllowedTypes = $allowedTypes !== []
            ? $allowedTypes
            : (is_array($configuredAllowedTypes) ? $configuredAllowedTypes : []);

        if (! collect($resolvedAllowedTypes)->contains(fn (string $allowedType): bool => $this->matchesAllowedType($user, $allowedType))) {
            return $this->deny($request, 403, 'This user type is not allowed to use this transport.');
        }

        return $next($request);
    }

    protected function deny(Request $request, int $status, string $message): Response
    {
        if (
            $request->expectsJson()
            || $request->is('api/*')
        ) {
            return response()->json([
                'message' => $message,
            ], $status);
        }

        abort($status, $message);
    }

    protected function matchesAllowedType(User $user, string $allowedType): bool
    {
        return $user->type === trim($allowedType);
    }
}
