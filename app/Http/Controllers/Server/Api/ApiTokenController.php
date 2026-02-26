<?php

namespace App\Http\Controllers\Server\Api;

use App\Actions\Server\Auth\MergeDeviceUserIntoPerson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Auth\StudioLoginRequest;
use App\Http\Requests\Server\Auth\StudioRegisterRequest;
use App\Models\Server\SanctumUser;
use App\Models\Server\User;
use App\TokenAbility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ApiTokenController extends Controller
{
    public function __construct(protected MergeDeviceUserIntoPerson $mergeDeviceUserIntoPerson) {}

    public function register(StudioRegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $deviceUser = $this->resolveDeviceUser($request);

        $user = SanctumUser::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'type' => 'person',
            'status' => 'active',
        ]);

        ($this->mergeDeviceUserIntoPerson)($deviceUser, $user);

        $token = $user->createToken('studio-api', [TokenAbility::Studio->value]);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function login(StudioLoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $deviceUser = $this->resolveDeviceUser($request);

        $user = SanctumUser::query()
            ->where('email', $data['email'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 422);
        }

        if ($user->type !== 'person') {
            return response()->json([
                'message' => 'This account is not permitted for Studio access.',
            ], 403);
        }

        ($this->mergeDeviceUserIntoPerson)($deviceUser, $user);

        $token = $user->createToken('studio-api', [TokenAbility::Studio->value]);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function logout(): JsonResponse
    {
        $user = request()->user();

        $user?->currentAccessToken()?->delete();

        return response()->json(status: 204);
    }

    protected function resolveDeviceUser(Request $request): ?User
    {
        $tokenUser = $request->user('sanctum');

        if ($tokenUser instanceof User && $tokenUser->type === 'device') {
            return $tokenUser;
        }

        $deviceId = $request->header('X-Device-Id') ?? $request->cookie('device_id');

        if (! is_string($deviceId) || $deviceId === '') {
            return null;
        }

        return SanctumUser::query()
            ->where('type', 'device')
            ->where('device_identifier', $deviceId)
            ->first();
    }
}
