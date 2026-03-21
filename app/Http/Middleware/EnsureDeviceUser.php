<?php

namespace App\Http\Middleware;

use App\Features\Actions\Auth\ResolveOrCreateGadgetUser;
use App\Models\Server\User;
use App\TokenAbility;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceUser
{
    public function __construct(protected ResolveOrCreateGadgetUser $resolveOrCreateGadgetUser) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() || $request->bearerToken()) {
            return $next($request);
        }

        $headerDeviceId = $request->header('X-Device-Id');
        $cookieDeviceId = $request->cookie('device_id');
        $shouldBootstrapTokenOnResponse = $this->shouldBootstrapTokenOnResponse($request, $headerDeviceId, $cookieDeviceId);

        $deviceId = $request->header('X-Device-Id')
            ?? $request->cookie('device_id')
            ?? (string) Str::uuid();

        if (! $request->cookie('device_id')) {
            Cookie::queue(cookie()->forever('device_id', $deviceId));
        }

        $requestUser = $request->user('sanctum');
        if (! $requestUser instanceof User) {
            $requestUser = $request->user();
        }

        $user = $this->resolveOrCreateGadgetUser->execute($this->gadgetUserContext($request), $requestUser instanceof User ? $requestUser : null);

        $request->attributes->set('initial_device_user_id', $user->id);

        if ($request->hasSession()) {
            $passkeySession = $request->session()->get('auth.device_passkey');

            if (is_array($passkeySession) && ((int) ($passkeySession['user_id'] ?? 0) !== (int) $user->id)) {
                $request->session()->forget('auth.device_passkey');
            }
        }

        Auth::login($user);

        $response = $next($request);

        if ($shouldBootstrapTokenOnResponse) {
            $token = $user->createToken('chat-device-bootstrap', [TokenAbility::Chat->value])->plainTextToken;

            $response->headers->set('X-Device-Id', (string) $deviceId);
            $response->headers->set('X-Api-Token', $token);
            $response->headers->set('X-Api-Token-Type', 'Bearer');

            $existingExposeHeaders = (string) $response->headers->get('Access-Control-Expose-Headers', '');
            $exposeHeaders = collect(explode(',', $existingExposeHeaders))
                ->map(fn (string $value): string => trim($value))
                ->filter(fn (string $value): bool => $value !== '')
                ->merge(['X-Device-Id', 'X-Api-Token', 'X-Api-Token-Type'])
                ->unique()
                ->implode(', ');

            if ($exposeHeaders !== '') {
                $response->headers->set('Access-Control-Expose-Headers', $exposeHeaders);
            }
        }

        return $response;
    }

    protected function shouldBootstrapTokenOnResponse(Request $request, ?string $headerDeviceId, ?string $cookieDeviceId): bool
    {
        $isApiStyleRequest = $request->expectsJson() || str_starts_with($request->path(), 'api/');

        if (! $isApiStyleRequest) {
            return false;
        }

        if ($request->bearerToken()) {
            return false;
        }

        return empty($headerDeviceId) && empty($cookieDeviceId);
    }

    /**
     * @return array{
     *     headers: array<string, mixed>,
     *     cookies: array<string, mixed>,
     *     user_agent: ?string,
     *     ip_address: ?string,
     *     expects_json: bool,
     *     path: string
     * }
     */
    protected function gadgetUserContext(Request $request): array
    {
        return [
            'headers' => [
                'X-Device-Id' => $request->header('X-Device-Id'),
                'X-App-Version' => $request->header('X-App-Version'),
                'X-Platform' => $request->header('X-Platform'),
            ],
            'cookies' => [
                'device_id' => $request->cookie('device_id'),
            ],
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'expects_json' => $request->expectsJson(),
            'path' => $request->path(),
        ];
    }
}
