<?php

namespace App\Features\Actions\Auth;

use App\Contracts\Users\UserRepository;
use App\Models\Server\User;
use App\Models\Server\UserAgent;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResolveOrCreateGadgetUser
{
    public function __construct(protected UserRepository $userRepository) {}

    /**
     * @param  array{
     *     headers?: array<string, mixed>,
     *     cookies?: array<string, mixed>,
     *     user_agent?: mixed,
     *     ip_address?: mixed,
     *     expects_json?: bool,
     *     path?: mixed
     * }  $context
     */
    public function execute(array $context, ?User $requestUser = null): User
    {
        if ($requestUser instanceof User && $requestUser->isGadget()) {
            $this->recordUserAgent($requestUser, $context);

            return $requestUser;
        }

        $deviceIdentifier = $this->resolveDeviceIdentifier($context);

        $user = UserAgent::query()
            ->with('user')
            ->where('device_identifier', $deviceIdentifier)
            ->latest('id')
            ->first()
            ?->user;

        if (! $user instanceof User || ! $user->isGadget()) {
            $user = $this->userRepository->create([
                'name' => 'Gadget User',
                'email' => "gadget-{$deviceIdentifier}@example.invalid",
                'password' => Hash::make(Str::random(48)),
                'type' => User::TypeGadget,
                'status' => 'active',
            ]);
        }

        if ($user->isGadget() && $user->type !== User::TypeGadget) {
            $user->forceFill([
                'type' => User::TypeGadget,
            ]);
            $this->userRepository->save($user);
        }

        $this->recordUserAgent($user, $context, $deviceIdentifier);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function resolveDeviceIdentifier(array $context): string
    {
        $headerDeviceId = $this->header($context, 'X-Device-Id');
        if (is_string($headerDeviceId) && trim($headerDeviceId) !== '') {
            return trim($headerDeviceId);
        }

        $cookieDeviceId = $this->cookie($context, 'device_id');
        if (is_string($cookieDeviceId) && trim($cookieDeviceId) !== '') {
            return trim($cookieDeviceId);
        }

        return (string) Str::uuid();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function recordUserAgent(User $user, array $context, ?string $deviceIdentifier = null): void
    {
        $resolvedDeviceIdentifier = $deviceIdentifier ?? $user->currentDeviceIdentifier();

        if ($resolvedDeviceIdentifier === null) {
            return;
        }

        UserAgent::query()->updateOrCreate(
            ['device_identifier' => $resolvedDeviceIdentifier],
            [
                'user_id' => $user->id,
                'kind' => $this->resolveKind($context),
                'device_identifier' => $resolvedDeviceIdentifier,
                'user_agent' => is_string($context['user_agent'] ?? null) ? $context['user_agent'] : null,
                'ip_address' => is_string($context['ip_address'] ?? null) ? $context['ip_address'] : null,
                'app_version' => $this->header($context, 'X-App-Version'),
                'platform' => $this->header($context, 'X-Platform'),
                'data' => [
                    'device_identifier' => $resolvedDeviceIdentifier,
                ],
                'metadata' => [
                    'native' => $this->header($context, 'X-NativePHP') !== null,
                ],
                'last_seen_at' => now(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function resolveKind(array $context): string
    {
        if ($this->header($context, 'X-NativePHP') !== null) {
            return 'native';
        }

        if (($context['expects_json'] ?? false) === true || str_starts_with((string) ($context['path'] ?? ''), 'api/')) {
            return 'api';
        }

        return 'web';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function header(array $context, string $key): mixed
    {
        $headers = $context['headers'] ?? [];

        if (! is_array($headers)) {
            return null;
        }

        return $headers[$key] ?? $headers[strtolower($key)] ?? null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function cookie(array $context, string $key): mixed
    {
        $cookies = $context['cookies'] ?? [];

        if (! is_array($cookies)) {
            return null;
        }

        return $cookies[$key] ?? null;
    }
}
