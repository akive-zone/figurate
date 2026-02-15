<?php

namespace App\Http\Controllers\Server\Auth;

use App\Http\Controllers\Controller;
use App\Models\Server\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
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
        $user = $this->resolveUser($provider, $socialUser);

        Auth::login($user);

        return redirect()->route('signal.index');
    }

    private function resolveUser(string $provider, SocialiteUser $socialUser): User
    {
        $existing = User::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($existing) {
            return $existing;
        }

        $current = Auth::user();

        if ($current) {
            $current->forceFill([
                'name' => $socialUser->getName() ?? $current->name,
                'email' => $socialUser->getEmail() ?? $current->email,
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'type' => 'person',
            ])->save();

            return $current;
        }

        return User::create([
            'name' => $socialUser->getName() ?? 'Person User',
            'email' => $socialUser->getEmail() ?? "person-{$provider}-{$socialUser->getId()}@example.invalid",
            'password' => Hash::make(Str::random(48)),
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'type' => 'person',
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
