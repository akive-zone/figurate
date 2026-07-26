<?php

namespace App\Http\Middleware;

use App\Models\Server\ApiIdempotencyRecord;
use App\Models\Server\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotentApiRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));

        if ($key === '') {
            return $next($request);
        }

        if (mb_strlen($key) > 255) {
            return response()->json([
                'message' => 'The Idempotency-Key header may not exceed 255 characters.',
            ], 422);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $scope = sprintf(
            '%s:%s',
            strtoupper($request->method()),
            $request->route()?->getActionName() ?? $request->path(),
        );
        $requestHash = hash('sha256', json_encode([
            'method' => strtoupper($request->method()),
            'scope' => $scope,
            'payload' => $request->all(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
        $lockName = 'api-idempotency:'.hash('sha256', "{$user->id}:{$scope}:{$key}");

        return Cache::lock($lockName, 15)->block(5, function () use (
            $key,
            $next,
            $request,
            $requestHash,
            $scope,
            $user,
        ): Response {
            $existing = ApiIdempotencyRecord::query()
                ->where('user_id', $user->id)
                ->where('scope', $scope)
                ->where('idempotency_key', $key)
                ->first();

            if ($existing instanceof ApiIdempotencyRecord) {
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    return response()->json([
                        'message' => 'The Idempotency-Key was already used with a different request.',
                    ], 409);
                }

                return response(
                    $existing->response_body,
                    $existing->status_code,
                    [
                        ...(is_array($existing->response_headers) ? $existing->response_headers : []),
                        'Idempotency-Replayed' => 'true',
                    ],
                );
            }

            $response = $next($request);

            if ($response->isSuccessful()) {
                ApiIdempotencyRecord::query()->create([
                    'user_id' => $user->id,
                    'scope' => $scope,
                    'idempotency_key' => $key,
                    'request_hash' => $requestHash,
                    'status_code' => $response->getStatusCode(),
                    'response_body' => (string) $response->getContent(),
                    'response_headers' => [
                        'Content-Type' => $response->headers->get('Content-Type', 'application/json'),
                    ],
                ]);
            }

            return $response;
        });
    }
}
