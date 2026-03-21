<?php

namespace Figurate\SocialAuth\Http\Controllers;

use App\Contracts\Users\UserRepository;
use App\Events\Accounts\AttachGadgetUserToUsersPrimaryAccountRequested;
use App\Events\Accounts\EnsurePrimaryAccountForUserRequested;
use App\Features\Actions\Auth\ResolveOrCreateGadgetUser;
use App\Http\Controllers\Controller;
use App\Models\Server\User;
use Figurate\AccountManager\Contracts\AccountContextFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;

class SocialiteController extends Controller
{
    public function __construct(
        protected AccountContextFactory $accountContextFactory,
        protected ResolveOrCreateGadgetUser $resolveOrCreateGadgetUser,
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

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->ensureProviderIsAllowed($provider);

        $socialUser = Socialite::driver($provider)->user();
        $subjectUser = $this->resolveSubjectUser($provider, $socialUser);
        $requestUser = $request->user('sanctum');

        if (! $requestUser instanceof User) {
            $requestUser = $request->user();
        }

        $gadgetUser = $this->resolveOrCreateGadgetUser->execute(
            $this->gadgetUserContext($request),
            $requestUser instanceof User ? $requestUser : null,
        );

        $this->synchronizeAccountContext($subjectUser, $gadgetUser);
        $this->attachSubjectIdentity($subjectUser, $provider, $socialUser);

        Auth::login($subjectUser);

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

    private function synchronizeAccountContext(User $subjectUser, ?User $gadgetUser): void
    {
        EnsurePrimaryAccountForUserRequested::dispatch($subjectUser);
        AttachGadgetUserToUsersPrimaryAccountRequested::dispatch($subjectUser, $gadgetUser);

        if ($this->accountContextFactory->forUser($subjectUser)->primaryAccount() === null) {
            throw new RuntimeException('A primary account could not be synchronized for the authenticated user.');
        }
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

    /**
     * @return array{
     *     headers: array<string, mixed>,
     *     cookies: array<string, mixed>,
     *     user_agent: ?string,
     *     ip_address: ?string,
     *     expects_json: bool,
     *     path: string
     * }
     */
    private function gadgetUserContext(Request $request): array
    {
        return [
            'headers' => [
                'X-Device-Id' => $request->header('X-Device-Id'),
                'X-App-Version' => $request->header('X-App-Version'),
                'X-Platform' => $request->header('X-Platform'),
                'X-NativePHP' => $request->header('X-NativePHP'),
            ],
            'cookies' => [
                'device_id' => $request->cookie('device_id'),
            ],
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'expects_json' => $request->expectsJson(),
            'path' => $request->path(),
        ];
    }
}
