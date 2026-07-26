<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Api\StoreApiCredentialRequest;
use App\Models\Server\PersonalAccessToken;
use App\Models\Server\SanctumUser;
use App\Models\Server\User;
use App\TokenAbility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CredentialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeCredentialManagement($user);

        $credentials = PersonalAccessToken::query()
            ->where('tokenable_type', SanctumUser::class)
            ->where('tokenable_id', $user->getKey())
            ->where('name', 'like', 'api:%')
            ->latest('created_at')
            ->get()
            ->map(fn (PersonalAccessToken $token): array => $this->mapCredential($token))
            ->all();

        return response()->json(['data' => $credentials]);
    }

    public function store(StoreApiCredentialRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeCredentialManagement($user);
        $validated = $request->validated();
        $tokenUser = SanctumUser::query()->findOrFail($user->getKey());
        $expiresAt = isset($validated['expires_at'])
            ? Carbon::parse((string) $validated['expires_at'])
            : null;
        $newToken = $tokenUser->createToken(
            'api:'.trim((string) $validated['name']),
            array_values(array_unique($validated['abilities'])),
            $expiresAt,
        );
        /** @var PersonalAccessToken $accessToken */
        $accessToken = $newToken->accessToken;

        return response()->json([
            'data' => [
                ...$this->mapCredential($accessToken),
                'token' => $newToken->plainTextToken,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    public function destroy(Request $request, string $credential): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeCredentialManagement($user);

        $token = PersonalAccessToken::query()
            ->where('ulid', $credential)
            ->where('tokenable_type', SanctumUser::class)
            ->where('tokenable_id', $user->getKey())
            ->where('name', 'like', 'api:%')
            ->firstOrFail();
        $token->delete();

        return response()->json(status: 204);
    }

    protected function authorizeCredentialManagement(User $user): void
    {
        $token = $user->currentAccessToken();

        abort_if(
            $token !== null
                && ! $user->tokenCan('*')
                && ! $user->tokenCan(TokenAbility::CredentialsManage->value),
            403,
            'The API credential cannot manage credentials.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapCredential(PersonalAccessToken $token): array
    {
        return [
            'id' => $token->ulid,
            'name' => str_starts_with((string) $token->name, 'api:')
                ? substr((string) $token->name, 4)
                : $token->name,
            'abilities' => is_array($token->abilities) ? $token->abilities : [],
            'last_used_at' => optional($token->last_used_at)?->toIso8601String(),
            'expires_at' => optional($token->expires_at)?->toIso8601String(),
            'created_at' => optional($token->created_at)?->toIso8601String(),
        ];
    }
}
