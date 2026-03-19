<?php

namespace App\Features\Actions\Auth;

use App\Models\Server\SanctumUser;
use App\Models\Server\User;
use App\Models\Server\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResolveOrCreateGadgetUser
{
    public function __invoke(Request $request): User
    {
        $requestUser = $request->user('sanctum') ?? $request->user();

        if ($requestUser instanceof User && $requestUser->isGadget()) {
            $this->recordUserAgent($requestUser, $request);

            return $requestUser;
        }

        $deviceIdentifier = $this->resolveDeviceIdentifier($request);

        $user = UserAgent::query()
            ->with('user')
            ->where('device_identifier', $deviceIdentifier)
            ->latest('id')
            ->first()
            ?->user;

        if (! $user instanceof User || ! $user->isGadget()) {
            $user = SanctumUser::query()
                ->where('device_identifier', $deviceIdentifier)
                ->first();
        }

        if (! $user instanceof User || ! $user->isGadget()) {
            $user = SanctumUser::query()->create([
                'name' => 'Gadget User',
                'email' => "gadget-{$deviceIdentifier}@example.invalid",
                'password' => Hash::make(Str::random(48)),
                'type' => User::TypeGadget,
                'status' => 'active',
                'device_identifier' => $deviceIdentifier,
            ]);
        }

        if ($user->isGadget() && $user->type !== User::TypeGadget) {
            $user->forceFill([
                'type' => User::TypeGadget,
            ])->save();
        }

        $this->recordUserAgent($user, $request, $deviceIdentifier);

        return $user;
    }

    protected function resolveDeviceIdentifier(Request $request): string
    {
        $headerDeviceId = $request->header('X-Device-Id');
        if (is_string($headerDeviceId) && trim($headerDeviceId) !== '') {
            return trim($headerDeviceId);
        }

        $cookieDeviceId = $request->cookie('device_id');
        if (is_string($cookieDeviceId) && trim($cookieDeviceId) !== '') {
            return trim($cookieDeviceId);
        }

        return (string) Str::uuid();
    }

    protected function recordUserAgent(User $user, Request $request, ?string $deviceIdentifier = null): void
    {
        $resolvedDeviceIdentifier = $deviceIdentifier
            ?? (is_string($user->device_identifier) && $user->device_identifier !== '' ? $user->device_identifier : null);

        if ($resolvedDeviceIdentifier === null) {
            return;
        }

        UserAgent::query()->updateOrCreate(
            ['device_identifier' => $resolvedDeviceIdentifier],
            [
                'user_id' => $user->id,
                'kind' => $this->resolveKind($request),
                'device_identifier' => $resolvedDeviceIdentifier,
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'app_version' => $request->header('X-App-Version'),
                'platform' => $request->header('X-Platform'),
                'data' => [
                    'device_identifier' => $resolvedDeviceIdentifier,
                ],
                'metadata' => [
                    'native' => $request->header('X-NativePHP') !== null,
                ],
                'last_seen_at' => now(),
            ],
        );

        if ($user->device_identifier !== $resolvedDeviceIdentifier) {
            $user->forceFill([
                'device_identifier' => $resolvedDeviceIdentifier,
            ])->save();
        }
    }

    protected function resolveKind(Request $request): string
    {
        if ($request->header('X-NativePHP') !== null) {
            return 'native';
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return 'api';
        }

        return 'web';
    }
}
