<?php

namespace Tests\Feature\Server\Api;

use App\Models\Server\User;
use App\TokenAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentUserProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_person_user_can_provision_an_agent_user_with_default_protocol_abilities(): void
    {
        $person = $this->makeUser('person');

        Sanctum::actingAs($person, [TokenAbility::Studio->value]);

        $response = $this->postJson('/api/auth/agents', [
            'name' => 'Remote Planner',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.type', 'agent')
            ->assertJsonPath('data.user.name', 'Remote Planner')
            ->assertJsonPath('data.abilities', TokenAbility::defaultAgentAbilities());

        $agentId = (int) $response->json('data.user.id');
        $agent = User::query()->findOrFail($agentId);

        $this->assertSame('agent', $agent->type);
        $this->assertSame('internal', $agent->provider);

        $tokenId = explode('|', (string) $response->json('data.token'))[0] ?? null;
        $token = PersonalAccessToken::query()->findOrFail((int) $tokenId);

        $this->assertSame(TokenAbility::defaultAgentAbilities(), $token->abilities);
    }

    public function test_a_device_user_cannot_provision_an_agent_user(): void
    {
        $device = $this->makeUser('device');

        Sanctum::actingAs($device, [TokenAbility::Chat->value]);

        $this->postJson('/api/auth/agents', [
            'name' => 'Blocked Agent',
        ])->assertForbidden();
    }

    protected function makeUser(string $type): User
    {
        return User::query()->create([
            'name' => ucfirst($type).' User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'type' => $type,
            'provider' => null,
            'provider_id' => null,
            'status' => 'active',
        ]);
    }
}
