<?php

namespace App\Http\Controllers\Api\Auth;

use App\Contracts\Users\UserRepository;
use App\Events\Server\Auth\SubjectAuthenticated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Auth\LoginRequest;
use App\TokenAbility;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function __construct(
        protected UserRepository $userRepository,
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $this->userRepository->findByEmail($data['email']);

        if (! $user || ! $user->isSubject() || ! Hash::check($data['password'], (string) $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 422);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'This account is not permitted for Studio access.',
            ], 403);
        }

        event(new Login('sanctum', $user, false));
        event(new SubjectAuthenticated($user, $request, 'login'));

        $token = $this->userRepository->issueToken($user, 'fig', [TokenAbility::Compose->value]);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }
}
