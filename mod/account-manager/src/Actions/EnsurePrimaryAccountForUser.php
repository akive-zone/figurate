<?php

namespace Figurate\AccountManager\Actions;

use App\Models\Server\User;
use Figurate\AccountManager\Models\Account;
use Figurate\AccountManager\Support\AccountContextFactory;
use Illuminate\Support\Facades\DB;

class EnsurePrimaryAccountForUser
{
    public function __construct(protected AccountContextFactory $accountContextFactory) {}

    public function __invoke(User $user): ?Account
    {
        $accountContext = $this->accountContextFactory->forUser($user);

        if (! $user->isSubject()) {
            return $accountContext->primaryAccount();
        }

        $account = $accountContext->primaryAccount();

        if ($account instanceof Account) {
            return $account;
        }

        return DB::transaction(function () use ($user): Account {
            $existingAccount = $this->accountContextFactory->forUser($user)->primaryAccount();

            if ($existingAccount instanceof Account) {
                return $existingAccount;
            }

            $account = Account::query()->create([
                'name' => $user->name,
                'status' => 'active',
            ]);

            $account->users()->syncWithoutDetaching([
                $user->id => [
                    'type' => 'owner',
                    'is_primary' => true,
                    'linked_at' => now(),
                    'unlinked_at' => null,
                ],
            ]);

            return $account;
        });
    }
}
