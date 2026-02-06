<?php

namespace App\Http\Controllers\Server\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Auth\StudioLoginRequest;
use App\Http\Requests\Server\Auth\StudioRegisterRequest;
use App\Models\Server\User;
use App\TokenAbility;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class ApiTokenController extends Controller
{
    public function register(StudioRegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'type' => 'person',
            'status' => 'active',
        ]);

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

        $user = User::query()
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
}
