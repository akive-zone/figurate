<?php

namespace Tests\Feature\A2a;

use App\Models\Server\User;
use App\TokenAbility;
use Figurate\AccountManager\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentUserProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_subject_user_can_provision_an_agent_user_with_default_protocol_abilities(): void
    {
        $person = $this->makeUser(User::TypeSubject);

        Sanctum::actingAs($person, [TokenAbility::Studio->value]);

        $response = $this->postJson('/api/auth/agents', [
            'name' => 'Remote Planner',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.type', User::TypeRobot)
            ->assertJsonPath('data.user.name', 'Remote Planner')
            ->assertJsonPath('data.abilities', TokenAbility::defaultAgentAbilities());

        $agentId = (int) $response->json('data.user.id');
        $agent = User::query()->findOrFail($agentId);

        $this->assertSame(User::TypeRobot, $agent->type);
        $this->assertDatabaseMissing('identities', [
            'user_id' => $agent->id,
        ]);

        $tokenId = explode('|', (string) $response->json('data.token'))[0] ?? null;
        $token = PersonalAccessToken::query()->findOrFail((int) $tokenId);

        $this->assertSame(TokenAbility::defaultAgentAbilities(), $token->abilities);
    }

    public function test_a_gadget_user_cannot_provision_an_agent_user_without_account_access(): void
    {
        $device = $this->makeUser(User::TypeGadget);

        Sanctum::actingAs($device, [TokenAbility::Chat->value]);

        $this->postJson('/api/auth/agents', [
            'name' => 'Blocked Agent',
        ])->assertForbidden();
    }

    public function test_an_account_linked_gadget_user_cannot_provision_an_agent_user(): void
    {
        $gadget = $this->makeUser(User::TypeGadget, 'gadget-owner@example.com');
        $account = Account::query()->create([
            'name' => 'Gadget Owner',
            'status' => 'active',
        ]);

        $gadget->accounts()->attach($account->id, [
            'relationship' => 'gadget',
            'is_primary' => true,
            'linked_at' => now(),
        ]);

        Sanctum::actingAs($gadget, [TokenAbility::Studio->value]);

        $this->postJson('/api/auth/agents', [
            'name' => 'Linked Gadget Agent',
        ])->assertForbidden();
    }

    protected function makeUser(string $type, ?string $email = null): User
    {
        return User::query()->create([
            'name' => ucfirst($type).' User',
            'email' => $email ?? fake()->unique()->safeEmail(),
            'password' => 'password',
            'type' => $type,
            'status' => 'active',
        ]);
    }
}
