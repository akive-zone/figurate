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
                    'chats' => $this->runtime->isNative() ? route('chat.index', [], false) : route('api.chats.index', [], false),
                    'chats_show_template' => $this->runtime->isNative()
                        ? route('chat.index', [], false)
                        : route('api.chats.show', ['chat' => '__CHAT__'], false),
                    'chats_message_turns_template' => $this->runtime->isNative() || ! Route::has('api.chats.message-turns')
                        ? route('chat.index', [], false)
                        : route('api.chats.message-turns', ['chat' => '__CHAT__', 'message' => '__MESSAGE__'], false),
                    'chats_threads_template' => $this->runtime->isNative()
                        ? route('chat.index', [], false)
                        : route('api.chats.threads', ['chat' => '__CHAT__'], false),
                    'chats_store' => $this->runtime->isNative() ? route('chat.index', [], false) : route('api.chats.store', [], false),
                    'chat_posts_template' => route('api.chats.posts', ['chat' => '__CHAT__'], false),
                    'create' => route('chat.create', [], false),
                    'show_template' => route('chat.show', ['channel' => '__CHANNEL__'], false),
                    'show_thread_template' => route('chat.thread', ['channel' => '__CHANNEL__', 'thread' => '__THREAD__'], false),
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
                    'google_redirect' => route('auth.redirect', ['provider' => 'google'], false),
                    'apple_redirect' => route('auth.redirect', ['provider' => 'apple'], false),
                    'passkeys_authentication_options' => Route::has('passkeys.authentication_options')
                        ? route('passkeys.authentication_options', [], false)
                        : null,
                    'passkeys_login' => Route::has('passkeys.login')
                        ? route('passkeys.login', [], false)
                        : null,
                    'passkeys_manage_generate_options' => Route::has('passkeys.manage.generate-options')
                        ? route('passkeys.manage.generate-options', [], false)
                        : null,
                    'passkeys_manage_store' => Route::has('passkeys.manage.store')
                        ? route('passkeys.manage.store', [], false)
                        : null,
                    'passkeys_manage_destroy_template' => Route::has('passkeys.manage.destroy')
                        ? route('passkeys.manage.destroy', ['passkey' => '__PASSKEY__'], false)
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
