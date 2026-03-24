<?php

namespace App\Features\Actions\Auth;

use App\Contracts\Users\UserRepository;
use App\Models\Server\User;
use App\Models\Server\UserAgent;

class ResolveGadgetUser
{
    public const GadgetUserHeader = 'X-Gadget-User-ID';

    public const LegacyDeviceHeader = 'X-Device-Id';

    public const DeviceCookie = 'device_id';

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
    public function execute(array $context, ?User $requestUser = null): ?User
    {
        if ($requestUser instanceof User && $requestUser->isGadget()) {
            $this->remember($requestUser, $context);

            return $requestUser;
        }

        $gadgetUserIdentifier = $this->resolveGadgetUserIdentifier($context);

        if (is_string($gadgetUserIdentifier) && trim($gadgetUserIdentifier) !== '') {
            $gadgetUser = $this->resolveByGadgetUserIdentifier(trim($gadgetUserIdentifier));

            if ($gadgetUser instanceof User) {
                $this->remember($gadgetUser, $context);

                return $gadgetUser;
            }
        }

        $deviceIdentifier = $this->resolveDeviceIdentifier($context);

        if (! is_string($deviceIdentifier) || trim($deviceIdentifier) === '') {
            return null;
        }

        $user = UserAgent::query()
            ->with('user')
            ->where('device_identifier', $deviceIdentifier)
            ->latest('id')
            ->first()
            ?->user;

        if (! $user instanceof User || ! $user->isGadget()) {
            return null;
        }

        if ($user->type !== User::TypeGadget) {
            $user->forceFill([
                'type' => User::TypeGadget,
            ]);
            $this->userRepository->save($user);
        }

        $this->remember($user, $context, $deviceIdentifier);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function resolveGadgetUserIdentifier(array $context): ?string
    {
        $gadgetUserId = $this->header($context, self::GadgetUserHeader);

        if (is_string($gadgetUserId) && trim($gadgetUserId) !== '') {
            return trim($gadgetUserId);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function resolveDeviceIdentifier(array $context): ?string
    {
        $headerDeviceId = $this->header($context, self::LegacyDeviceHeader);
        if (is_string($headerDeviceId) && trim($headerDeviceId) !== '') {
            return trim($headerDeviceId);
        }

        $cookieDeviceId = $this->cookie($context, self::DeviceCookie);
        if (is_string($cookieDeviceId) && trim($cookieDeviceId) !== '') {
            return trim($cookieDeviceId);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function remember(User $user, array $context, ?string $deviceIdentifier = null): void
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
                    'gadget_user_uuid' => $user->uuid,
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

    protected function resolveByGadgetUserIdentifier(string $gadgetUserIdentifier): ?User
    {
        $gadgetUser = $this->userRepository->findByUuid($gadgetUserIdentifier);

        if (! $gadgetUser instanceof User && ctype_digit($gadgetUserIdentifier)) {
            $gadgetUser = $this->userRepository->findById((int) $gadgetUserIdentifier);
        }

        return $gadgetUser instanceof User && $gadgetUser->isGadget()
            ? $gadgetUser
            : null;
    }
}
