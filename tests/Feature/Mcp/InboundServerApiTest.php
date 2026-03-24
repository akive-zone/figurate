<?php

namespace Tests\Feature\Mcp;

use App\Models\Server\User;
use App\TokenAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InboundServerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_untrusted_remote_context_server_urls_on_create(): void
    {
        config()->set('services.mcp.trust', [
            'allow_http' => false,
            'allow_private_network' => false,
        ]);
        $user = User::factory()->create();
        Sanctum::actingAs($user, [TokenAbility::Compose->value]);

        $response = $this->postJson(route('api.context-servers.store'), [
            'context_type' => 'user',
            'context_id' => 'me',
            'server' => 'planner',
            'transport' => 'remote',
            'endpoint_url' => 'http://127.0.0.1:3000/mcp',
            'allowed_tools' => ['search'],
        ]);

        $response->assertStatus(422)
            ->assertInvalid([
                'endpoint_url' => 'Plain HTTP URLs are not allowed by policy.',
            ]);
    }

    public function test_it_rejects_untrusted_remote_context_server_urls_on_update(): void
    {
        config()->set('services.mcp.trust', [
            'allow_http' => false,
            'allow_private_network' => false,
        ]);
        $user = User::factory()->create();
        Sanctum::actingAs($user, [TokenAbility::Compose->value]);

        $contextServer = $user->contextServers()->create([
            'server' => 'planner',
            'label' => 'Planner',
            'enabled' => true,
            'priority' => 0,
            'transport' => 'remote',
            'endpoint_url' => 'https://agents.example/mcp',
            'allowed_tools' => ['search'],
        ]);

        $response = $this->patchJson(route('api.context-servers.update', ['server' => $contextServer->id]), [
            'endpoint_url' => 'http://127.0.0.1:3000/mcp',
        ]);

        $response->assertStatus(422)
            ->assertInvalid([
                'endpoint_url' => 'Plain HTTP URLs are not allowed by policy.',
            ]);

        $contextServer->refresh();
        $this->assertSame('https://agents.example/mcp', $contextServer->endpoint_url);
    }
}
