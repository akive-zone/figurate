<?php

namespace Figurate\AccountManager;

use App\Contracts\Accounts\AccountContextFactory as AccountContextFactoryContract;
use App\Events\Accounts\AttachGadgetUserToUsersPrimaryAccountRequested;
use App\Events\Accounts\EnsurePrimaryAccountForUserRequested;
use App\Models\Server\User;
use Figurate\AccountManager\Listeners\AttachGadgetUserToUsersPrimaryAccountListener;
use Figurate\AccountManager\Listeners\EnsurePrimaryAccountForUserListener;
use Figurate\AccountManager\Models\Account;
use Figurate\AccountManager\Models\AccountUser;
use Figurate\AccountManager\Support\AccountContextFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AccountManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AccountContextFactoryContract::class, AccountContextFactory::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');
        $this->loadRoutesFrom(dirname(__DIR__).'/routes/api.php');

        User::resolveRelationUsing('accountUsers', function (User $user): HasMany {
            return $user->hasMany(AccountUser::class, 'user_id');
        });

        User::resolveRelationUsing('accounts', function (User $user): BelongsToMany {
            return $user->belongsToMany(Account::class, 'account_users', 'user_id', 'account_id')
                ->withPivot(['relationship', 'is_primary', 'linked_at', 'unlinked_at'])
                ->withTimestamps();
        });

        Event::listen(
            EnsurePrimaryAccountForUserRequested::class,
            EnsurePrimaryAccountForUserListener::class,
        );

        Event::listen(
            AttachGadgetUserToUsersPrimaryAccountRequested::class,
            AttachGadgetUserToUsersPrimaryAccountListener::class,
        );
    }
}
