<?php

namespace App\Http\Controllers\Api\Auth;

use App\Contracts\Users\UserRepository;
use App\Features\Actions\Auth\ResolveOrCreateWidgetUser;
use App\Features\Actions\Auth\ResolveWidgetUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Passkey\StorePasskeyRequest;
use App\Models\Server\User;
use App\Support\Passkeys\PasskeyCeremonyStore;
use App\TokenAbility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Models\Passkey;

class PasskeyController extends Controller
{
    public function __construct(
        protected PasskeyCeremonyStore $passkeyCeremonyStore,
        protected UserRepository $userRepository,
        protected ResolveWidgetUser $resolveWidgetUser,
        protected ResolveOrCreateWidgetUser $resolveOrCreateWidgetUser,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return response()->json([
            'data' => $user->passkeys()
                ->latest('id')
                ->get()
                ->map(fn (Passkey $passkey): array => $this->passkeyPayload($passkey))
                ->values()
                ->all(),
        ]);
    }

    public function generateRegisterOptions(Request $request): JsonResponse
    {
        $user = $this->resolvePasskeyRegistrationUser($request, true);
        abort_unless($user instanceof User, 403);

        $generatePasskeyOptionsAction = app(GeneratePasskeyRegisterOptionsAction::class);
        $options = $generatePasskeyOptionsAction->execute($user);
        $ceremonyId = $this->passkeyCeremonyStore->storeRegistrationOptions($user, $options);

        $response = response()->json([
            'data' => [
                'ceremony_id' => $ceremonyId,
                'options' => json_decode($options, true),
            ],
        ]);

        if ($user->isWidget()) {
            $this->attachWidgetUserHeaders($response, $user);
        }

        return $response;
    }

    public function store(StorePasskeyRequest $request): JsonResponse
    {
        $user = $this->resolvePasskeyRegistrationUser($request);
        abort_unless($user instanceof User, 403);

        $validated = $request->validated();
        $storePasskeyAction = app(StorePasskeyAction::class);
        $registrationPayload = $this->passkeyCeremonyStore->consumeRegistrationOptions((string) $validated['ceremony_id']);

        if (! is_array($registrationPayload)) {
            throw ValidationException::withMessages([
                'ceremony_id' => 'The passkey ceremony has expired or is invalid.',
            ]);
        }

        $ceremonyUserId = (int) ($registrationPayload['user_id'] ?? 0);
        $passkeyOptionsJson = (string) ($registrationPayload['options'] ?? '');

        if ($user->id !== $ceremonyUserId) {
            if (! $user->isWidget()) {
                throw ValidationException::withMessages([
                    'ceremony_id' => 'The passkey ceremony is not valid for the authenticated user.',
                ]);
            }

            $user = $this->userRepository->findById($ceremonyUserId);
        }

        if (! $user instanceof User || $passkeyOptionsJson === '') {
            throw ValidationException::withMessages([
                'ceremony_id' => 'The passkey ceremony has expired or is invalid.',
            ]);
        }

        $relyingPartyId = (string) (config('passkeys.relying_party.id') ?: $request->getHost());

        try {
            $passkey = $storePasskeyAction->execute(
                $user,
                (string) $validated['passkey'],
                $passkeyOptionsJson,
                $relyingPartyId,
                ['name' => (string) ($validated['name'] ?? Str::random(10))],
            );
        } catch (\Throwable $exception) {
            report($exception);
            Log::warning('Failed to store passkey registration response.', [
                'user_id' => $user->id,
                'host' => (string) $request->getHost(),
                'relying_party_id' => $relyingPartyId,
            ]);

            throw ValidationException::withMessages([
                'passkey' => __('passkeys::passkeys.error_something_went_wrong_generating_the_passkey'),
            ]);
        }

        $payload = [
            'data' => $this->passkeyPayload($passkey),
        ];

        if ($user->isWidget()) {
            $payload['token'] = $this->userRepository->issueToken($user, 'widget-api', [TokenAbility::Compose->value]);
            $payload['token_type'] = 'Bearer';
            $payload['widget_user_id'] = $user->uuid;
        }

        return response()->json($payload, 201);
    }

    public function destroy(Request $request, int $passkey): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $user->passkeys()->where('id', $passkey)->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array{id:int,name:string,last_used_at:?string,created_at:?string}
     */
    protected function passkeyPayload(Passkey $passkey): array
    {
        return [
            'id' => $passkey->id,
            'name' => (string) $passkey->name,
            'last_used_at' => $passkey->last_used_at?->toIso8601String(),
            'created_at' => $passkey->created_at?->toIso8601String(),
        ];
    }

    protected function resolvePasskeyRegistrationUser(Request $request, bool $createWidget = false): ?User
    {
        $user = $request->user('sanctum');

        if (! $user instanceof User) {
            $user = $request->user('passport');
        }

        if (! $user instanceof User) {
            $user = $request->user();
        }

        if ($user instanceof User) {
            return $user;
        }

        $this->ensureDeviceCookie($request);

        $context = ResolveWidgetUser::contextFromRequest($request);
        $widgetUser = $this->resolveWidgetUser->execute($context);

        if ($widgetUser instanceof User) {
            return $widgetUser;
        }

        if (! $createWidget) {
            return null;
        }

        return $this->resolveOrCreateWidgetUser->execute($context);
    }

    protected function ensureDeviceCookie(Request $request): void
    {
        if ($request->cookie(ResolveWidgetUser::DeviceCookie)) {
            return;
        }

        $deviceIdentifier = $request->header(ResolveWidgetUser::DeviceIdentifierHeader) ?? (string) Str::uuid();

        Cookie::queue(cookie()->forever(ResolveWidgetUser::DeviceCookie, $deviceIdentifier));
    }

    protected function attachWidgetUserHeaders(JsonResponse $response, User $user): void
    {
        $response->headers->set(ResolveWidgetUser::WidgetUserHeader, (string) $user->uuid);

        $existingExposeHeaders = (string) $response->headers->get('Access-Control-Expose-Headers', '');
        $exposeHeaders = collect(explode(',', $existingExposeHeaders))
            ->map(fn (string $value): string => trim($value))
            ->filter(fn (string $value): bool => $value !== '')
            ->merge([ResolveWidgetUser::WidgetUserHeader])
            ->unique()
            ->implode(', ');

        if ($exposeHeaders !== '') {
            $response->headers->set('Access-Control-Expose-Headers', $exposeHeaders);
        }
    }
}
