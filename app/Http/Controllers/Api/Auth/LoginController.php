<?php

namespace App\Http\Controllers\Api\Auth;

use App\Contracts\Users\UserRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Auth\LoginRequest;
use App\TokenAbility;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function __construct(
        protected UserRepository $userRepository,
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $subjectUser = $this->userRepository->findByEmail($data['email']);

        if (! $subjectUser || ! $subjectUser->isSubject() || ! Hash::check($data['password'], (string) $subjectUser->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 422);
        }

        if ($subjectUser->status !== 'active') {
            return response()->json([
                'message' => 'This account is not permitted for Studio access.',
            ], 403);
        }

        event(new Login('sanctum', $subjectUser, false));

        $token = $this->userRepository->issueToken($subjectUser, 'studio-api', [TokenAbility::Studio->value]);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'device_id' => $this->requestDeviceIdentifier($request),
            'user' => $subjectUser,
        ]);
    }

    protected function requestDeviceIdentifier(Request $request): ?string
    {
        return $request->header('X-Device-Id') ?? $request->cookie('device_id');
    }
}
