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

        $this->postJson('/api/auth/passkeys/options/register')
            ->assertOk()
            ->assertJsonPath('data.options.challenge', 'register-challenge')
            ->assertJsonPath('data.options.rp.name', 'Figurate')
            ->assertJsonStructure([
                'data' => ['ceremony_id', 'options'],
            ]);
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

        $ceremonyId = (string) $this->postJson('/api/auth/passkeys/options/register')
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
