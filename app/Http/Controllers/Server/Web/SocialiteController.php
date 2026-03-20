<?php

namespace App\Http\Controllers\Server\Web;

use App\Contracts\Accounts\AccountContextFactory;
use App\Events\Accounts\AttachGadgetUserToUsersPrimaryAccountRequested;
use App\Events\Accounts\EnsurePrimaryAccountForUserRequested;
use App\Features\Actions\Auth\ResolveOrCreateGadgetUser;
use App\Http\Controllers\Controller;
use App\Models\Server\SanctumUser;
use App\Models\Server\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;

class SocialiteController extends Controller
{
    public function __construct(
        protected AccountContextFactory $accountContextFactory,
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
        $subjectUser = $this->resolveSubjectUser($provider, $socialUser);
        $gadgetUser = $this->resolveOrCreateGadgetUser->execute(request());
        $this->synchronizeAccountContext($subjectUser, $gadgetUser);

        Auth::login($subjectUser);

        return redirect()->route('chat.index');
    }

    private function resolveSubjectUser(string $provider, SocialiteUser $socialUser): User
    {
        $existing = SanctumUser::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($existing) {
            return $existing;
        }

        $email = $socialUser->getEmail();

        if (is_string($email) && trim($email) !== '') {
            $subjectUser = SanctumUser::query()->where('email', trim($email))->first();

            if ($subjectUser) {
                $subjectUser->forceFill([
                    'name' => $socialUser->getName() ?? $subjectUser->name,
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'status' => 'active',
                ])->save();

                return $subjectUser;
            }
        }

        return SanctumUser::query()->create([
            'name' => $socialUser->getName() ?? 'Subject User',
            'email' => is_string($email) && trim($email) !== '' ? trim($email) : null,
            'password' => null,
            'type' => User::TypeSubject,
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
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

    private function ensureProviderIsAllowed(string $provider): void
    {
        if (! in_array($provider, $this->allowedProviders, true)) {
            abort(404);
        }
    }
}
