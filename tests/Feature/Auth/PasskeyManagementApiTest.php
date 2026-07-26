<?php

namespace Tests\Feature\Auth;

use App\Models\Server\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Models\Passkey;
use Tests\TestCase;

class PasskeyManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_the_authenticated_users_passkeys(): void
    {
        $user = $this->makeUser();
        Passkey::factory()->for($user, 'authenticatable')->create(['name' => 'Laptop']);
        Passkey::factory()->for($user, 'authenticatable')->create(['name' => 'Phone']);

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/passkeys')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Phone')
            ->assertJsonPath('data.1.name', 'Laptop');
    }

    public function test_it_generates_registration_options_with_a_ceremony_id(): void
    {
        $user = $this->makeUser();

        Sanctum::actingAs($user);

        $optionsJson = json_encode([
            'challenge' => 'register-challenge',
            'rp' => ['name' => 'Figurate'],
        ], JSON_THROW_ON_ERROR);

        $this->mock(GeneratePasskeyRegisterOptionsAction::class, function (MockInterface $mock) use ($user, $optionsJson): void {
            $mock->shouldReceive('execute')
                ->once()
                ->with($user)
                ->andReturn($optionsJson);
        });

        $this->postJson('/api/auth/passkeys/options')
            ->assertOk()
            ->assertJsonPath('data.options.challenge', 'register-challenge')
            ->assertJsonPath('data.options.rp.name', 'Figurate')
            ->assertJsonStructure([
                'data' => ['ceremony_id', 'options'],
            ]);
    }

    public function test_a_guest_can_generate_registration_options_and_become_a_widget_candidate(): void
    {
        $optionsJson = json_encode([
            'challenge' => 'guest-register-challenge',
            'rp' => ['name' => 'Figurate'],
        ], JSON_THROW_ON_ERROR);

        $this->mock(GeneratePasskeyRegisterOptionsAction::class, function (MockInterface $mock) use ($optionsJson): void {
            $mock->shouldReceive('execute')
                ->once()
                ->withArgs(fn (User $user): bool => $user->isWidget())
                ->andReturn($optionsJson);
        });

        $response = $this->postJson('/api/auth/passkeys/options');

        $response->assertOk()
            ->assertJsonPath('data.options.challenge', 'guest-register-challenge')
            ->assertHeader('X-Widget-User-ID')
            ->assertHeaderMissing('X-Gadget-User-ID');

        $widgetUserId = (string) $response->headers->get('X-Widget-User-ID');
        $widgetUser = User::query()->where('uuid', $widgetUserId)->firstOrFail();

        $this->assertSame(User::TypeWidget, $widgetUser->type);
    }

    public function test_it_stores_a_passkey_using_cached_ceremony_options(): void
    {
        $user = $this->makeUser();

        Sanctum::actingAs($user);

        $optionsJson = json_encode([
            'challenge' => 'register-challenge',
            'rp' => ['name' => 'Figurate'],
        ], JSON_THROW_ON_ERROR);

        $this->mock(GeneratePasskeyRegisterOptionsAction::class, function (MockInterface $mock) use ($user, $optionsJson): void {
            $mock->shouldReceive('execute')
                ->once()
                ->with($user)
                ->andReturn($optionsJson);
        });

        $ceremonyId = (string) $this->postJson('/api/auth/passkeys/options')
            ->assertOk()
            ->json('data.ceremony_id');

        $storedPasskey = Passkey::factory()->for($user, 'authenticatable')->create([
            'name' => 'Office Key',
        ]);

        $this->mock(StorePasskeyAction::class, function (MockInterface $mock) use ($user, $optionsJson, $storedPasskey): void {
            $mock->shouldReceive('execute')
                ->once()
                ->withArgs(function (User $authenticatable, string $passkeyJson, string $storedOptionsJson, string $relyingPartyId, array $attributes) use ($user, $optionsJson): bool {
                    return $authenticatable->is($user)
                        && $passkeyJson === '{"id":"credential"}'
                        && $storedOptionsJson === $optionsJson
                        && $relyingPartyId !== ''
                        && ($attributes['name'] ?? null) === 'Office Key';
                })
                ->andReturn($storedPasskey);
        });

        $this->postJson('/api/auth/passkeys', [
            'name' => 'Office Key',
            'ceremony_id' => $ceremonyId,
            'passkey' => '{"id":"credential"}',
        ])->assertCreated()
            ->assertJsonPath('data.id', $storedPasskey->id)
            ->assertJsonPath('data.name', 'Office Key');
    }

    public function test_a_guest_receives_a_widget_token_after_storing_a_passkey(): void
    {
        $optionsJson = json_encode([
            'challenge' => 'guest-register-challenge',
            'rp' => ['name' => 'Figurate'],
        ], JSON_THROW_ON_ERROR);

        $this->mock(GeneratePasskeyRegisterOptionsAction::class, function (MockInterface $mock) use ($optionsJson): void {
            $mock->shouldReceive('execute')
                ->once()
                ->withArgs(fn (User $user): bool => $user->isWidget())
                ->andReturn($optionsJson);
        });

        $optionsResponse = $this->postJson('/api/auth/passkeys/options')
            ->assertOk();

        $widgetUserId = (string) $optionsResponse->headers->get('X-Widget-User-ID');
        $widgetUser = User::query()->where('uuid', $widgetUserId)->firstOrFail();
        $ceremonyId = (string) $optionsResponse->json('data.ceremony_id');

        $storedPasskey = Passkey::factory()->for($widgetUser, 'authenticatable')->create([
            'name' => 'Guest Key',
        ]);

        $this->mock(StorePasskeyAction::class, function (MockInterface $mock) use ($widgetUser, $optionsJson, $storedPasskey): void {
            $mock->shouldReceive('execute')
                ->once()
                ->withArgs(function (User $authenticatable, string $passkeyJson, string $storedOptionsJson, string $relyingPartyId, array $attributes) use ($widgetUser, $optionsJson): bool {
                    return $authenticatable->is($widgetUser)
                        && $authenticatable->isWidget()
                        && $passkeyJson === '{"id":"guest-credential"}'
                        && $storedOptionsJson === $optionsJson
                        && $relyingPartyId !== ''
                        && ($attributes['name'] ?? null) === 'Guest Key';
                })
                ->andReturn($storedPasskey);
        });

        $this->withHeader('X-Widget-User-ID', $widgetUserId)
            ->postJson('/api/auth/passkeys', [
                'name' => 'Guest Key',
                'ceremony_id' => $ceremonyId,
                'passkey' => '{"id":"guest-credential"}',
            ])->assertCreated()
            ->assertJsonPath('data.id', $storedPasskey->id)
            ->assertJsonPath('data.name', 'Guest Key')
            ->assertJsonPath('widget_user_id', $widgetUserId)
            ->assertJsonMissingPath('gadget_user_id')
            ->assertJsonPath('token_type', 'Bearer');
    }

    public function test_it_deletes_a_passkey_owned_by_the_authenticated_user(): void
    {
        $user = $this->makeUser();
        $passkey = Passkey::factory()->for($user, 'authenticatable')->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/auth/passkeys/{$passkey->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('passkeys', [
            'id' => $passkey->id,
        ]);
    }

    protected function makeUser(): User
    {
        return User::query()->create([
            'name' => 'Passkey Tester',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'type' => User::TypeSubject,
            'status' => 'active',
        ]);
    }
}
