<?php

namespace App\Http\Controllers\Api\Auth;

use App\Contracts\Users\UserRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Auth\UpdateCurrentUserRequest;
use App\Models\Server\User;
use App\TokenAbility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    public function __construct(
        protected UserRepository $userRepository,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->userPayload($user),
        ]);
    }

    public function update(UpdateCurrentUserRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->fill($request->validated());
        $this->userRepository->save($user);

        return response()->json([
            'data' => $this->userPayload($user),
        ]);
    }

    /**
     * @return array{id:string,name:?string,status:string,abilities:list<string>}
     */
    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->uuid,
            'name' => $user->name,
            'status' => $user->status,
            'abilities' => $this->abilities($user),
        ];
    }

    /**
     * @return list<string>
     */
    protected function abilities(User $user): array
    {
        return collect(TokenAbility::cases())
            ->filter(fn (TokenAbility $ability): bool => $user->tokenCan($ability->value))
            ->map(fn (TokenAbility $ability): string => $ability->value)
            ->values()
            ->all();
    }
}
