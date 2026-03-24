<?php

namespace App\Http\Controllers\Server\Api\Auth;

use App\Contracts\Users\UserRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Auth\RegisterRequest;
use App\Models\Server\User;
use App\TokenAbility;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function __construct(
        protected UserRepository $userRepository,
    ) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $this->userRepository->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'type' => User::TypeSubject,
            'status' => 'active',
        ]);
        event(new Registered($user));

        $token = $this->userRepository->issueToken($user, 'session', []);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'device_id' => $this->requestDeviceIdentifier($request),
            'user' => $user,
        ]);
    }

    protected function requestDeviceIdentifier(Request $request): ?string
    {
        return $request->header('X-Device-Id') ?? $request->cookie('device_id');
    }
}
