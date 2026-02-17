<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function rootView(Request $request): string
    {
        return \app_is_native_runtime() ? 'app-native' : $this->rootView;
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'runtime' => [
                'is_native' => \app_is_native_runtime(),
                'signal_base_path' => \app_is_native_runtime() ? '/signal' : '',
                'signal_index_path' => \app_is_native_runtime() ? '/signal' : '/',
                'signal_routes' => [
                    'index' => route('signal.index'),
                    'create' => route('signal.chat.create'),
                    'show_template' => route('signal.chat.show', ['channel' => '__CHANNEL__']),
                ],
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'type' => $user->type,
                    'device_identifier' => $user->device_identifier,
                ] : null,
                'routes' => [
                    'google_redirect' => route('auth.redirect', ['provider' => 'google']),
                    'apple_redirect' => route('auth.redirect', ['provider' => 'apple']),
                ],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
