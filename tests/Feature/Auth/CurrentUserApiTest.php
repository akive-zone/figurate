<?php

namespace Tests\Feature\Auth;

use App\Models\Server\User;
use App\TokenAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CurrentUserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_read_the_current_user_surface(): void
    {
        $user = User::factory()->create([
            'name' => 'Current User',
        ]);

        Sanctum::actingAs($user, [
            TokenAbility::Compose->value,
            TokenAbility::McpUse->value,
        ]);

        $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $user->uuid,
                    'name' => 'Current User',
                    'status' => 'active',
                    'abilities' => [
                        TokenAbility::Compose->value,
                        TokenAbility::McpUse->value,
                    ],
                ],
            ]);
    }

    public function test_an_authenticated_user_can_update_their_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Before',
        ]);

        Sanctum::actingAs($user, [TokenAbility::Compose->value]);

        $this->patchJson('/api/auth/user', [
            'name' => 'After',
            'type' => User::TypeRobot,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->uuid)
            ->assertJsonPath('data.name', 'After')
            ->assertJsonMissingPath('data.type');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'After',
            'type' => User::TypeSubject,
        ]);
    }

    public function test_current_user_endpoints_require_authentication(): void
    {
        $this->getJson('/api/auth/user')->assertUnauthorized();
        $this->patchJson('/api/auth/user', ['name' => 'Updated'])->assertUnauthorized();
    }

    public function test_current_user_name_must_be_present_when_submitted(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [TokenAbility::Compose->value]);

        $this->patchJson('/api/auth/user', [
            'name' => null,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }
}
