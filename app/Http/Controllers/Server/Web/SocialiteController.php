<?php

namespace App\Http\Controllers\Server\Web;

use App\Actions\Server\Auth\AttachGadgetUserToAccount;
use App\Actions\Server\Auth\ResolveOrCreateGadgetUser;
use App\Http\Controllers\Controller;
use App\Models\Server\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    public function __construct(
        protected AttachGadgetUserToAccount $attachGadgetUserToAccount,
        protected ResolveOrCreateGadgetUser $resolveOrCreateGadgetUser,
    ) {}

    /**
     * @var list<string>
     */
    private array $allowedProviders = ['google', 'apple'];

    public function redirect(string $provider): RedirectResponse
    {
        $this->ensureProviderIsAllowed($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $this->ensureProviderIsAllowed($provider);

        $socialUser = Socialite::driver($provider)->user();
        $account = $this->resolveAccount($provider, $socialUser);
        $gadgetUser = ($this->resolveOrCreateGadgetUser)(request());

        ($this->attachGadgetUserToAccount)($gadgetUser, $account);
        Auth::login($gadgetUser);

        return redirect()->route('chat.index');
    }

    private function resolveAccount(string $provider, SocialiteUser $socialUser): Account
    {
        $existing = Account::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($existing) {
            return $existing;
        }

        $email = $socialUser->getEmail();

        if (is_string($email) && trim($email) !== '') {
            $account = Account::query()->where('email', trim($email))->first();

            if ($account) {
                $account->forceFill([
                    'name' => $socialUser->getName() ?? $account->name,
                    'email' => trim($email),
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'status' => 'active',
                ])->save();

                return $account;
            }
        }

        return Account::query()->create([
            'name' => $socialUser->getName() ?? 'Account User',
            'email' => is_string($email) && trim($email) !== '' ? trim($email) : null,
            'password' => null,
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'status' => 'active',
        ]);
    }

    private function ensureProviderIsAllowed(string $provider): void
    {
        if (! in_array($provider, $this->allowedProviders, true)) {
            abort(404);
        }
    }
}
