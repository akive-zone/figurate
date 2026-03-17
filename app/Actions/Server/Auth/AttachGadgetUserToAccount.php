<?php

namespace App\Actions\Server\Auth;

use App\Models\Server\Account;
use App\Models\Server\AccountUser;
use App\Models\Server\User;
use Illuminate\Support\Facades\DB;

class AttachGadgetUserToAccount
{
    public function __invoke(?User $gadgetUser, Account $account): void
    {
        if (! $gadgetUser instanceof User || ! $gadgetUser->isGadget()) {
            return;
        }

        DB::transaction(function () use ($gadgetUser, $account): void {
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
    }
}
