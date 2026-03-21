<?php

namespace Figurate\AccountManager\Support;

use App\Models\Server\User;
use Figurate\AccountManager\Contracts\AccountContext as AccountContextContract;
use Figurate\AccountManager\Models\Account;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountContext implements AccountContextContract
{
    public function __construct(protected User $user) {}

    public function accountUsers(): HasMany
    {
        /** @var HasMany */
        return $this->user->accountUsers();
    }

    public function accounts(): BelongsToMany
    {
        /** @var BelongsToMany */
        return $this->user->accounts();
    }

    public function activeAccounts(): BelongsToMany
    {
        return $this->accounts()->wherePivotNull('unlinked_at');
    }

    public function hasAccount(): bool
    {
        if ($this->user->relationLoaded('accounts')) {
            /** @var Collection<int, Account> $accounts */
            $accounts = $this->user->getRelation('accounts');

            return $accounts->contains(function (Account $account): bool {
                return $account->pivot === null || $account->pivot->unlinked_at === null;
            });
        }

        return $this->activeAccounts()->exists();
    }

    public function primaryAccount(): ?Account
    {
        if ($this->user->relationLoaded('accounts')) {
            /** @var Collection<int, Account> $accounts */
            $accounts = $this->user->getRelation('accounts');

            /** @var ?Account $account */
            $account = $accounts
                ->filter(fn (Account $account): bool => $account->pivot === null || $account->pivot->unlinked_at === null)
                ->sortByDesc(fn (Account $account): int => (int) ($account->pivot?->is_primary ?? false))
                ->sortByDesc(fn (Account $account): int => $account->pivot?->linked_at?->getTimestamp() ?? 0)
                ->first();

            return $account;
        }

        return $this->activeAccounts()
            ->orderByPivotDesc('is_primary')
            ->orderByPivotDesc('linked_at')
            ->first();
    }

    public function canActAsHuman(): bool
    {
        return $this->user->isSubject();
    }
}
