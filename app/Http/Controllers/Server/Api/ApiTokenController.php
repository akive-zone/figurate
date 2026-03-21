<?php

namespace App\Http\Controllers\Server\Api;

use App\Contracts\Users\UserRepository;
use App\Events\Accounts\AttachGadgetUserToUsersPrimaryAccountRequested;
use App\Events\Accounts\EnsurePrimaryAccountForUserRequested;
use App\Features\Actions\Auth\ResolveOrCreateGadgetUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Auth\StudioLoginRequest;
use App\Http\Requests\Server\Auth\StudioRegisterRequest;
use App\Models\Server\User;
use App\TokenAbility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ApiTokenController extends Controller
{
    public function __construct(
        protected ResolveOrCreateGadgetUser $resolveOrCreateGadgetUser,
        protected UserRepository $userRepository,
    ) {}

    public function register(StudioRegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $gadgetUser = $this->resolveOrCreateGadgetUser->execute(
            $this->gadgetUserContext($request),
            $request->user('sanctum') instanceof User ? $request->user('sanctum') : ($request->user() instanceof User ? $request->user() : null),
        );
        $subjectUser = $this->userRepository->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'type' => User::TypeSubject,
            'status' => 'active',
        ]);
        $this->synchronizeAccountContext($subjectUser, $gadgetUser);

        $token = $this->userRepository->issueToken($subjectUser, 'studio-api', [TokenAbility::Studio->value]);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'device_id' => $gadgetUser->currentDeviceIdentifier(),
            'user' => $subjectUser,
        ]);
    }

    public function login(StudioLoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $gadgetUser = $this->resolveOrCreateGadgetUser->execute(
            $this->gadgetUserContext($request),
            $request->user('sanctum') instanceof User ? $request->user('sanctum') : ($request->user() instanceof User ? $request->user() : null),
        );

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

        $this->synchronizeAccountContext($subjectUser, $gadgetUser);

        $token = $this->userRepository->issueToken($subjectUser, 'studio-api', [TokenAbility::Studio->value]);

        return response()->json([
            'token' => $token,
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

    /**
     * @return array{
     *     headers: array<string, mixed>,
     *     cookies: array<string, mixed>,
     *     user_agent: ?string,
     *     ip_address: ?string,
     *     expects_json: bool,
     *     path: string
     * }
     */
    protected function gadgetUserContext(Request $request): array
    {
        return [
            'headers' => [
                'X-Device-Id' => $request->header('X-Device-Id'),
                'X-App-Version' => $request->header('X-App-Version'),
                'X-Platform' => $request->header('X-Platform'),
                'X-NativePHP' => $request->header('X-NativePHP'),
            ],
            'cookies' => [
                'device_id' => $request->cookie('device_id'),
            ],
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'expects_json' => $request->expectsJson(),
            'path' => $request->path(),
        ];
    }
}
