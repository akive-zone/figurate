<?php

namespace App\Features\Actions\Auth;

use App\Contracts\Users\UserRepository;
use App\Models\Server\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResolveOrCreateGadgetUser
{
    public function __construct(
        protected ResolveGadgetUser $resolveGadgetUser,
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
        $user = $this->resolveGadgetUser->execute($context, $requestUser);

        if ($user instanceof User) {
            return $user;
        }

        $deviceIdentifier = $this->resolveGadgetUser->resolveDeviceIdentifier($context) ?? (string) Str::uuid();

        $user = $this->userRepository->create([
            'name' => 'Gadget User',
            'email' => "gadget-{$deviceIdentifier}@example.invalid",
            'password' => Hash::make(Str::random(48)),
            'type' => User::TypeGadget,
            'status' => 'active',
        ]);

        $this->resolveGadgetUser->remember($user, $context, $deviceIdentifier);

        return $user;
    }
}
