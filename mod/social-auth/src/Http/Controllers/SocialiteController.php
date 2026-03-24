<?php

namespace Figurate\SocialAuth\Http\Controllers;

use App\Contracts\Users\UserRepository;
use App\Http\Controllers\Controller;
use App\Models\Server\User;
use Figurate\AccountManager\Contracts\AccountContextFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    public function __construct(
        protected AccountContextFactory $accountContextFactory,
        protected UserRepository $userRepository,
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
        $subjectUser = $this->resolveSubjectUser($provider, $socialUser);

        Auth::login($subjectUser);
        $this->attachSubjectIdentity($subjectUser, $provider, $socialUser);

        return redirect()->route('chat.index');
    }

    private function resolveSubjectUser(string $provider, SocialiteUser $socialUser): User
    {
        $existing = $this->userRepository->findByIdentity($provider, $socialUser->getId());

        if ($existing) {
            return $existing;
        }

        $email = $socialUser->getEmail();

        if (is_string($email) && trim($email) !== '') {
            $subjectUser = $this->userRepository->findByEmail(trim($email));

            if ($subjectUser) {
                $subjectUser->forceFill([
                    'name' => $socialUser->getName() ?? $subjectUser->name,
                    'status' => 'active',
                ]);
                $this->userRepository->save($subjectUser);

                return $subjectUser;
            }
        }

        return $this->userRepository->create([
            'name' => $socialUser->getName() ?? 'Subject User',
            'email' => is_string($email) && trim($email) !== '' ? trim($email) : null,
            'password' => Hash::make(Str::random(48)),
            'type' => User::TypeSubject,
            'status' => 'active',
        ]);
    }

    private function attachSubjectIdentity(User $subjectUser, string $provider, SocialiteUser $socialUser): void
    {
        $primaryAccount = $this->accountContextFactory->forUser($subjectUser)->primaryAccount();
        $email = $socialUser->getEmail();
        $nickname = $socialUser->getNickname();

        $identity = $this->userRepository->attachIdentity(
            $subjectUser,
            $provider,
            $socialUser->getId(),
            [
                'payload' => [
                    'email' => is_string($email) && trim($email) !== '' ? trim($email) : null,
                    'username' => is_string($nickname) && trim($nickname) !== '' ? trim($nickname) : null,
                    'avatar_url' => $socialUser->getAvatar(),
                    'tokens' => [
                        'access' => $socialUser->token ?? null,
                        'refresh' => $socialUser->refreshToken ?? null,
                    ],
                    'scopes' => $socialUser->approvedScopes ?? null,
                    'claims' => [
                        'name' => $socialUser->getName(),
                        'nickname' => $socialUser->getNickname(),
                        'email' => $socialUser->getEmail(),
                    ],
                ],
                'linked_at' => now(),
                'last_used_at' => now(),
            ],
        );

        if ($primaryAccount !== null) {
            $primaryAccount->identities()->syncWithoutDetaching([
                $identity->getKey() => [
                    'linked_at' => now(),
                    'unlinked_at' => null,
                ],
            ]);
        }
    }

    private function ensureProviderIsAllowed(string $provider): void
    {
        if (! in_array($provider, $this->allowedProviders, true)) {
            abort(404);
        }
    }
}
