<?php

namespace App\Http\Controllers\Api\Auth;

use App\Contracts\Users\UserRepository;
use App\Events\Server\Auth\SubjectAuthenticated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Auth\RegisterRequest;
use App\Models\Server\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
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
        event(new SubjectAuthenticated($user, $request, 'register'));

        $token = $this->userRepository->issueToken($user, 'session', []);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }
}
