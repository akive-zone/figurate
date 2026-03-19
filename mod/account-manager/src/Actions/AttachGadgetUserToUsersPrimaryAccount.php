<?php

namespace Figurate\AccountManager\Actions;

use App\Models\Server\User;
use Figurate\AccountManager\Models\Account;
use Figurate\AccountManager\Models\AccountUser;
use Figurate\AccountManager\Support\AccountContextFactory;
use Illuminate\Support\Facades\DB;

class AttachGadgetUserToUsersPrimaryAccount
{
    public function __construct(protected AccountContextFactory $accountContextFactory) {}

    public function __invoke(?User $gadgetUser, User $user): ?Account
    {
        if (! $gadgetUser instanceof User || ! $gadgetUser->isGadget()) {
            return null;
        }

        $account = $this->accountContextFactory->forUser($user)->primaryAccount();

        if (! $account instanceof Account) {
            return null;
        }

        DB::transaction(function () use ($account, $gadgetUser): void {
            AccountUser::query()
                ->where('user_id', $gadgetUser->id)
                ->whereNull('unlinked_at')
                ->where('account_id', '!=', $account->id)
                ->update([
                    'unlinked_at' => now(),
                    'updated_at' => now(),
                    'is_primary' => false,
                ]);

            AccountUser::query()->updateOrCreate(
                [
                    'account_id' => $account->id,
                    'user_id' => $gadgetUser->id,
                ],
                [
                    'relationship' => 'gadget',
                    'is_primary' => true,
                    'linked_at' => now(),
                    'unlinked_at' => null,
                ],
            );

            if ($gadgetUser->name === null || trim($gadgetUser->name) === '' || $gadgetUser->name === 'Gadget User') {
                $gadgetUser->forceFill([
                    'name' => $account->name ?: $gadgetUser->name,
                ])->save();
            }
        });

        return $account;
    }
}
