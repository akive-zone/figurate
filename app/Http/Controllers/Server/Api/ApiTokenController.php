<?php

namespace App\Http\Controllers\Server\Api;

use App\Actions\Server\Auth\ResolveOrCreateGadgetUser;
use App\Events\Accounts\AttachGadgetUserToUsersPrimaryAccountRequested;
use App\Events\Accounts\EnsurePrimaryAccountForUserRequested;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Auth\StudioLoginRequest;
use App\Http\Requests\Server\Auth\StudioRegisterRequest;
use App\Models\Server\SanctumUser;
use App\Models\Server\User;
use App\TokenAbility;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class ApiTokenController extends Controller
{
    public function __construct(
        protected ResolveOrCreateGadgetUser $resolveOrCreateGadgetUser,
    ) {}

    public function register(StudioRegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $gadgetUser = ($this->resolveOrCreateGadgetUser)($request);
        $subjectUser = SanctumUser::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'type' => User::TypeSubject,
            'status' => 'active',
        ]);
        $this->synchronizeAccountContext($subjectUser, $gadgetUser);

        $token = $subjectUser->createToken('studio-api', [TokenAbility::Studio->value]);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'device_id' => $gadgetUser->currentDeviceIdentifier(),
            'user' => $subjectUser,
        ]);
    }

    public function login(StudioLoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $gadgetUser = ($this->resolveOrCreateGadgetUser)($request);

        $subjectUser = SanctumUser::query()
            ->where('email', $data['email'])
            ->first();

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

        $this->synchronizeAccountContext($subjectUser, $gadgetUser);

        $token = $subjectUser->createToken('studio-api', [TokenAbility::Studio->value]);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'device_id' => $gadgetUser->currentDeviceIdentifier(),
            'user' => $subjectUser,
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

    protected function synchronizeAccountContext(User $subjectUser, ?User $gadgetUser): void
    {
        EnsurePrimaryAccountForUserRequested::dispatch($subjectUser);
        AttachGadgetUserToUsersPrimaryAccountRequested::dispatch($subjectUser, $gadgetUser);
    }
}
