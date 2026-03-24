<?php

namespace App\Features\Actions\Auth;

use App\Contracts\Users\UserRepository;
use App\Models\Server\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResolveOrCreateWidgetUser
{
    public function __construct(
        protected ResolveWidgetUser $resolveWidgetUser,
        protected UserRepository $userRepository,
    ) {}

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
        $user = $this->resolveWidgetUser->execute($context, $requestUser);

        if ($user instanceof User) {
            return $user;
        }

        $deviceIdentifier = $this->resolveWidgetUser->resolveDeviceIdentifier($context) ?? (string) Str::uuid();

        $user = $this->userRepository->create([
            'name' => 'Widget User',
            'email' => "widget-{$deviceIdentifier}@example.invalid",
            'password' => Hash::make(Str::random(48)),
            'type' => User::TypeWidget,
            'status' => 'active',
        ]);

        $this->resolveWidgetUser->remember($user, $context, $deviceIdentifier);

        return $user;
    }
}
