<?php

namespace App\Repositories\Users;

use App\Contracts\Users\UserRepository;
use App\Models\Server\Identity;
use App\Models\Server\SanctumUser;
use App\Models\Server\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EloquentUserRepository implements UserRepository
{
    public function findById(int $id): ?User
    {
        return User::query()->find($id);
    }

    public function findByUuid(string $uuid): ?User
    {
        return User::query()->where('uuid', $uuid)->first();
    }

    public function findIdByUuid(string $uuid): ?int
    {
        $id = User::query()->where('uuid', $uuid)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function findByIdentity(string $provider, string $providerSubject): ?User
    {
        return User::query()
            ->whereHas('identities', function ($query) use ($provider, $providerSubject): void {
                $query
                    ->where('provider', $provider)
                    ->where('provider_subject', $providerSubject);
            })
            ->first();
    }

    public function create(array $attributes): User
    {
        return SanctumUser::query()->create($attributes);
    }

    public function save(User $user): User
    {
        $user->save();

        return $user;
    }

    public function attachIdentity(User $user, string $provider, string $providerSubject, array $attributes = []): Identity
    {
        /** @var Identity $identity */
        $identity = Identity::query()->updateOrCreate(
            [
                'provider' => $provider,
                'provider_subject' => $providerSubject,
            ],
            [
                'payload' => $attributes['payload'] ?? null,
                'linked_at' => $attributes['linked_at'] ?? now(),
                'last_used_at' => $attributes['last_used_at'] ?? now(),
            ],
        );

        $user->identities()->syncWithoutDetaching([
            $identity->getKey() => [
                'type' => $attributes['type'] ?? null,
                'payload' => $attributes['relation_payload'] ?? null,
                'linked_at' => $attributes['linked_at'] ?? now(),
                'unlinked_at' => null,
            ],
        ]);

        return $identity;
    }

    public function issueToken(User $user, string $tokenName, array $abilities): string
    {
        $tokenUser = SanctumUser::query()->findOrFail($user->getKey());

        return $tokenUser->createToken($tokenName, $abilities)->plainTextToken;
    }

    public function findManyByIds(array $ids): Collection
    {
        $normalizedIds = collect($ids)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($normalizedIds === []) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $normalizedIds)
            ->get()
            ->values();
    }

    public function deleteAuthTokens(User $user): void
    {
        SanctumUser::query()->find($user->getKey())?->tokens()->delete();

        if (Schema::hasTable('oauth_access_tokens')) {
            DB::table('oauth_access_tokens')
                ->where('user_id', $user->getKey())
                ->update(['revoked' => true]);
        }
    }
}
