<?php

namespace Tests\Unit;

use App\Models\Server\User;
use App\Support\Passkeys\FindPasskeyToAuthenticateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPasskeys\Models\Passkey;
use Tests\TestCase;

class FindPasskeyToAuthenticateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_passkey_for_an_active_user(): void
    {
        $user = $this->makeUser('active');
        $passkey = Passkey::factory()->for($user, 'authenticatable')->create();

        $action = new class($passkey) extends FindPasskeyToAuthenticateAction
        {
            public function __construct(protected Passkey $passkey) {}

            protected function resolvePasskey(string $publicKeyCredentialJson, string $passkeyOptionsJson): ?Passkey
            {
                return $this->passkey;
            }
        };

        $resolvedPasskey = $action->execute('{"id":"credential"}', '{"challenge":"challenge"}');

        $this->assertSame($passkey->id, $resolvedPasskey?->id);
    }

    public function test_it_denies_the_passkey_for_a_merged_user(): void
    {
        $user = $this->makeUser('merged');
        $passkey = Passkey::factory()->for($user, 'authenticatable')->create();

        $action = new class($passkey) extends FindPasskeyToAuthenticateAction
        {
            public function __construct(protected Passkey $passkey) {}

            protected function resolvePasskey(string $publicKeyCredentialJson, string $passkeyOptionsJson): ?Passkey
            {
                return $this->passkey;
            }
        };

        $resolvedPasskey = $action->execute('{"id":"credential"}', '{"challenge":"challenge"}');

        $this->assertNull($resolvedPasskey);
        $this->assertDatabaseHas('activity_log', [
            'event' => 'auth.passkey_login_denied_merged_user',
        ]);
    }

    public function test_it_denies_the_passkey_for_a_non_active_subject_user(): void
    {
        $user = $this->makeUser('suspended');
        $passkey = Passkey::factory()->for($user, 'authenticatable')->create();

        $action = new class($passkey) extends FindPasskeyToAuthenticateAction
        {
            public function __construct(protected Passkey $passkey) {}

            protected function resolvePasskey(string $publicKeyCredentialJson, string $passkeyOptionsJson): ?Passkey
            {
                return $this->passkey;
            }
        };

        $resolvedPasskey = $action->execute('{"id":"credential"}', '{"challenge":"challenge"}');

        $this->assertNull($resolvedPasskey);
        $this->assertDatabaseHas('activity_log', [
            'event' => 'auth.passkey_login_denied_inactive_user',
        ]);
    }

    protected function makeUser(string $status): User
    {
        return User::query()->create([
            'name' => 'Passkey User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'type' => User::TypeSubject,
            'status' => $status,
        ]);
    }
}
