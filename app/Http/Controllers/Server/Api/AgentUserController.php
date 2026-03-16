<?php

namespace App\Http\Controllers\Server\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Auth\StoreAgentUserRequest;
use App\Models\Server\SanctumUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AgentUserController extends Controller
{
    public function store(StoreAgentUserRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $name = trim((string) $validated['name']);
        $email = isset($validated['email'])
            ? trim((string) $validated['email'])
            : 'agent-'.Str::lower((string) Str::ulid()).'@example.invalid';
        $tokenName = isset($validated['token_name'])
            ? trim((string) $validated['token_name'])
            : Str::slug($name, '-').'-agent-token';
        $abilities = collect($validated['abilities'] ?? [])
            ->filter(fn (mixed $ability): bool => is_string($ability) && trim($ability) !== '')
            ->map(fn (string $ability): string => trim($ability))
            ->unique()
            ->values()
            ->all();

        $agent = SanctumUser::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(Str::random(48)),
            'type' => 'agent',
            'provider' => 'internal',
            'provider_id' => null,
            'status' => 'active',
        ]);

        $token = $agent->createToken($tokenName, $abilities);

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'abilities' => $abilities,
                'user' => [
                    'id' => $agent->id,
                    'uuid' => $agent->uuid,
                    'name' => $agent->name,
                    'email' => $agent->email,
                    'type' => $agent->type,
                    'status' => $agent->status,
                ],
            ],
        ], 201);
    }
}
