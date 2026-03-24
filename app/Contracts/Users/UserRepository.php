<?php

namespace App\Contracts\Users;

use App\Models\Server\Identity;
use App\Models\Server\User;
use Illuminate\Support\Collection;

interface UserRepository
{
    public function findById(int $id): ?User;

    public function findByUuid(string $uuid): ?User;

    public function findIdByUuid(string $uuid): ?int;

    public function findByEmail(string $email): ?User;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User;

    public function save(User $user): User;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function attachIdentity(User $user, string $provider, string $providerSubject, array $attributes = []): Identity;

    /**
     * @param  array<int, string>  $abilities
     */
    public function issueToken(User $user, string $tokenName, array $abilities): string;

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, User>
     */
    public function findManyByIds(array $ids): Collection;

    public function deleteAuthTokens(User $user): void;
}
