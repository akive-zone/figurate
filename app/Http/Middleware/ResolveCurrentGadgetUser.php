<?php

namespace App\Http\Middleware;

use App\Features\Actions\Auth\ResolveGadgetUser;
use App\Models\Server\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentGadgetUser
{
    public const AttributeName = 'resolved_gadget_user';

    public function __construct(protected ResolveGadgetUser $resolveGadgetUser) {}

    public function handle(Request $request, Closure $next): Response
    {
        $requestUser = $request->user('sanctum');

        if (! $requestUser instanceof User) {
            $requestUser = $request->user();
        }

        $gadgetUser = $this->resolveGadgetUser->execute(
            $this->gadgetUserContext($request),
            $requestUser instanceof User ? $requestUser : null,
        );

        if ($gadgetUser instanceof User) {
            $request->attributes->set(self::AttributeName, $gadgetUser);
        }

        return $next($request);
    }

    public static function resolvedUser(Request $request): ?User
    {
        $user = $request->attributes->get(self::AttributeName);

        return $user instanceof User && $user->isGadget() ? $user : null;
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
                ResolveGadgetUser::GadgetUserHeader => $request->header(ResolveGadgetUser::GadgetUserHeader),
                ResolveGadgetUser::LegacyDeviceHeader => $request->header(ResolveGadgetUser::LegacyDeviceHeader),
                'X-App-Version' => $request->header('X-App-Version'),
                'X-Platform' => $request->header('X-Platform'),
                'X-NativePHP' => $request->header('X-NativePHP'),
            ],
            'cookies' => [
                ResolveGadgetUser::DeviceCookie => $request->cookie(ResolveGadgetUser::DeviceCookie),
            ],
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'expects_json' => $request->expectsJson(),
            'path' => $request->path(),
        ];
    }
}
