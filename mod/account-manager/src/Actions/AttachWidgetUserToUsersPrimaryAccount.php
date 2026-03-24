<?php

namespace Figurate\AccountManager\Actions;

use App\Models\Server\User;
use Figurate\AccountManager\Models\Account;
use Figurate\AccountManager\Models\AccountUser;
use Figurate\AccountManager\Support\AccountContextFactory;
use Illuminate\Support\Facades\DB;

class AttachWidgetUserToUsersPrimaryAccount
{
    public function __construct(protected AccountContextFactory $accountContextFactory) {}

    public function __invoke(?User $widgetUser, User $user): ?Account
    {
        if (! $widgetUser instanceof User || ! $widgetUser->isWidget()) {
            return null;
        }

        $account = $this->accountContextFactory->forUser($user)->primaryAccount();

        if (! $account instanceof Account) {
            return null;
        }

        DB::transaction(function () use ($account, $widgetUser): void {
            AccountUser::query()
                ->where('user_id', $widgetUser->id)
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
                    'user_id' => $widgetUser->id,
                ],
                [
                    'relationship' => 'widget',
                    'is_primary' => true,
                    'linked_at' => now(),
                    'unlinked_at' => null,
                ],
            );

            if ($widgetUser->name === null || trim($widgetUser->name) === '' || $widgetUser->name === 'Widget User') {
                $widgetUser->forceFill([
                    'name' => $account->name ?: $widgetUser->name,
                ])->save();
            }
        });

        return $account;
    }
}
