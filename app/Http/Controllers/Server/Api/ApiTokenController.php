<?php

namespace App\Http\Controllers\Server\Api;

use App\Actions\Server\Auth\AttachGadgetUserToAccount;
use App\Actions\Server\Auth\ResolveOrCreateGadgetUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Auth\StudioLoginRequest;
use App\Http\Requests\Server\Auth\StudioRegisterRequest;
use App\Models\Server\Account;
use App\TokenAbility;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class ApiTokenController extends Controller
{
    public function __construct(
        protected AttachGadgetUserToAccount $attachGadgetUserToAccount,
        protected ResolveOrCreateGadgetUser $resolveOrCreateGadgetUser,
    ) {}

    public function register(StudioRegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $gadgetUser = ($this->resolveOrCreateGadgetUser)($request);

        $account = Account::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => 'active',
        ]);

        ($this->attachGadgetUserToAccount)($gadgetUser, $account);

        $token = $gadgetUser->createToken('studio-api', [TokenAbility::Studio->value]);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'device_id' => $gadgetUser->currentDeviceIdentifier(),
            'user' => $gadgetUser,
            'account' => $account,
        ]);
    }

    public function login(StudioLoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $gadgetUser = ($this->resolveOrCreateGadgetUser)($request);

        $account = Account::query()
            ->where('email', $data['email'])
            ->first();

        if (! $account || ! Hash::check($data['password'], (string) $account->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 422);
        }

        if ($account->status !== 'active') {
            return response()->json([
                'message' => 'This account is not permitted for Studio access.',
            ], 403);
        }

        ($this->attachGadgetUserToAccount)($gadgetUser, $account);

        $token = $gadgetUser->createToken('studio-api', [TokenAbility::Studio->value]);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'device_id' => $gadgetUser->currentDeviceIdentifier(),
            'user' => $gadgetUser,
            'account' => $account,
        ]);
    }

    public function logout(): JsonResponse
    {
        $user = request()->user();

        if ($user && method_exists($user, 'currentAccessToken')) {
            $user->currentAccessToken()?->delete();
        }

        if ($user && method_exists($user, 'token')) {
            $token = $user->token();

            if ($token && method_exists($token, 'revoke')) {
                $token->revoke();
            }
        }

        return response()->json(status: 204);
    }
}
