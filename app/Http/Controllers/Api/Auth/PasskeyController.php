<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Passkey\StorePasskeyRequest;
use App\Models\Server\User;
use App\Support\Passkeys\PasskeyCeremonyStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Models\Passkey;

class PasskeyController extends Controller
{
    public function __construct(protected PasskeyCeremonyStore $passkeyCeremonyStore) {}

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
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $generatePasskeyOptionsAction = app(GeneratePasskeyRegisterOptionsAction::class);
        $options = $generatePasskeyOptionsAction->execute($user);
        $ceremonyId = $this->passkeyCeremonyStore->storeRegistrationOptions($user, $options);

        return response()->json([
            'data' => [
                'ceremony_id' => $ceremonyId,
                'options' => json_decode($options, true),
            ],
        ]);
    }

    public function store(StorePasskeyRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validated();
        $storePasskeyAction = app(StorePasskeyAction::class);
        $passkeyOptionsJson = $this->passkeyCeremonyStore->consumeRegistrationOptions(
            $user,
            (string) $validated['ceremony_id'],
        );

        if (! is_string($passkeyOptionsJson) || $passkeyOptionsJson === '') {
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

        return response()->json([
            'data' => $this->passkeyPayload($passkey),
        ], 201);
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
}
