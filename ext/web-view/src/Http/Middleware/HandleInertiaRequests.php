<?php

namespace Figurate\WebView\Http\Middleware;

use App\Models\Server\User;
use App\Support\Runtime\AppRuntime;
use Figurate\AccountManager\Support\AccountContextFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        protected AccountContextFactory $accountContextFactory,
        protected AppRuntime $runtime,
    ) {}

    protected $rootView = 'web-view::app';

    public function rootView(Request $request): string
    {
        return $this->runtime->rootView($this->rootView);
    }

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $passkeySession = $request->session()->get('auth.device_passkey');
        $isVerifiedDevicePasskeySession = $user
            && method_exists($user, 'isGadget')
            && $user->isGadget()
            && is_array($passkeySession)
            && ((int) ($passkeySession['user_id'] ?? 0) === (int) $user->id);
        $account = $user instanceof User
            ? $this->accountContextFactory->forUser($user)->primaryAccount()
            : null;

        return [
            ...parent::share($request),
            'runtime' => [
                'host' => $this->runtime->host(),
                'is_native' => $this->runtime->isNative(),
                'base_path' => $this->runtime->isNative() ? '/chat' : '',
                'index_path' => $this->runtime->isNative() ? '/chat' : '/',
                'routes' => [
                    'index' => route('chat.index', [], false),
                    'conversations' => $this->runtime->isNative() ? route('chat.index', [], false) : route('api.spaces.index', [], false),
                    'conversation_show_template' => $this->runtime->isNative()
                        ? route('chat.index', [], false)
                        : route('api.threads.show', ['thread' => '__CONVERSATION__'], false),
                    'conversation_post_turns_template' => $this->runtime->isNative() || ! Route::has('api.threads.posts.turns.index')
                        ? route('chat.index', [], false)
                        : route('api.threads.posts.turns.index', ['thread' => '__CONVERSATION__', 'post' => '__POST__'], false),
                    'conversation_threads_template' => $this->runtime->isNative()
                        ? route('chat.index', [], false)
                        : route('api.spaces.threads.index', ['space' => '__CONVERSATION__'], false),
                    'conversation_store' => $this->runtime->isNative() ? route('chat.index', [], false) : route('api.form.store', [], false),
                    'conversation_posts_template' => route('api.spaces.posts.index', ['space' => '__CONVERSATION__'], false),
                    'create' => route('chat.create', [], false),
                    'show_template' => route('chat.show', ['space' => '__SPACE__'], false),
                    'show_thread_template' => route('chat.thread', ['space' => '__SPACE__', 'thread' => '__THREAD__'], false),
                ],
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'type' => $user->type,
                    'device_identifier' => method_exists($user, 'currentDeviceIdentifier')
                        ? $user->currentDeviceIdentifier()
                        : $user->device_identifier,
                ] : null,
                'account' => $account ? [
                    'id' => $account->id,
                    'uuid' => $account->uuid,
                    'name' => $account->name,
                    'status' => $account->status,
                ] : null,
                'passkeys' => $user && method_exists($user, 'passkeys')
                    ? $user->passkeys()
                        ->get()
                        ->map(fn ($passkey): array => [
                            'id' => $passkey->id,
                            'name' => $passkey->name,
                            'last_used_at' => optional($passkey->last_used_at)?->toIso8601String(),
                        ])
                        ->values()
                        ->all()
                    : [],
                'routes' => [
                    'passkeys_authentication_options' => Route::has('passkeys.authentication_options')
                        ? route('passkeys.authentication_options', [], false)
                        : null,
                    'passkeys_login' => Route::has('passkeys.login')
                        ? route('passkeys.login', [], false)
                        : null,
                    'passkeys_manage_index' => Route::has('api.passkeys.index')
                        ? route('api.passkeys.index', [], false)
                        : null,
                    'passkeys_manage_generate_options' => Route::has('api.passkeys.register-options')
                        ? route('api.passkeys.register-options', [], false)
                        : null,
                    'passkeys_manage_store' => Route::has('api.passkeys.store')
                        ? route('api.passkeys.store', [], false)
                        : null,
                    'passkeys_manage_destroy_template' => Route::has('api.passkeys.destroy')
                        ? route('api.passkeys.destroy', ['passkey' => '__PASSKEY__'], false)
                        : null,
                ],
                'passkey_session' => [
                    'active' => $isVerifiedDevicePasskeySession,
                    'passkey_id' => $isVerifiedDevicePasskeySession ? (int) ($passkeySession['passkey_id'] ?? 0) : null,
                    'authenticated_at' => $isVerifiedDevicePasskeySession
                        ? ($passkeySession['authenticated_at'] ?? null)
                        : null,
                ],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
