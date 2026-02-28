<?php

namespace App\Http\Controllers\Server\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Passkey\StorePasskeyRequest;
use App\Models\Server\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;

class PasskeyController extends Controller
{
    public function generateOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $generatePasskeyOptionsAction = app(GeneratePasskeyRegisterOptionsAction::class);
        $options = $generatePasskeyOptionsAction->execute($user);

        session()->put('passkey-registration-options', $options);

        return response()->json(json_decode($options, true));
    }

    public function store(StorePasskeyRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validated();
        $storePasskeyAction = app(StorePasskeyAction::class);
        $storedOptions = session()->pull('passkey-registration-options');

        $passkeyOptionsJson = is_string($storedOptions) && $storedOptions !== ''
            ? $storedOptions
            : (string) $validated['options'];
        $relyingPartyId = (string) (config('passkeys.relying_party.id') ?: $request->getHost());

        try {
            $storePasskeyAction->execute(
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

        return back()->with('success', 'Passkey added successfully.');
    }

    public function destroy(Request $request, int $passkey): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $user->passkeys()->where('id', $passkey)->delete();

        return back()->with('success', 'Passkey deleted successfully.');
    }
}
