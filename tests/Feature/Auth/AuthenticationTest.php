<?php

namespace Tests\Feature\Auth;

use App\Models\Server\User;
use App\Models\Server\UserAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_can_link_an_existing_gadget_user_without_creating_a_new_one(): void
    {
        $gadgetUser = User::query()->create([
            'name' => 'Existing Gadget',
            'email' => 'gadget-register-1@example.invalid',
            'password' => 'password123',
            'type' => User::TypeGadget,
            'status' => 'active',
        ]);

        UserAgent::query()->create([
            'user_id' => $gadgetUser->id,
            'kind' => 'api',
            'device_identifier' => 'machine-register-1',
            'last_seen_at' => now()->subMinute(),
        ]);

        $response = $this->withHeader('X-Gadget-User-ID', (string) $gadgetUser->uuid)
            ->postJson('/api/auth/register', [
                'name' => 'Studio Owner',
                'email' => 'owner@example.com',
                'password' => 'password123',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.type', User::TypeSubject)
            ->assertJsonPath('gadget_user_id', $gadgetUser->uuid);

        $this->assertDatabaseHas('user_agents', [
            'user_id' => $gadgetUser->id,
            'device_identifier' => 'machine-register-1',
        ]);

        $this->assertSame(1, User::query()->where('type', User::TypeGadget)->count());
        $this->assertDatabaseMissing('users', [
            'email' => 'gadget-owner@example.com',
        ]);
    }

    public function test_login_resolves_an_existing_gadget_user_without_creating_a_new_one(): void
    {
        $subjectUser = User::query()->create([
            'name' => 'Studio Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'type' => User::TypeSubject,
            'status' => 'active',
        ]);
        $gadgetUser = User::query()->create([
            'name' => 'Existing Gadget',
            'email' => 'gadget-login-1@example.invalid',
            'password' => 'password123',
            'type' => User::TypeGadget,
            'status' => 'active',
        ]);

        UserAgent::query()->create([
            'user_id' => $gadgetUser->id,
            'kind' => 'api',
            'device_identifier' => 'gadget-login-1',
            'last_seen_at' => now()->subMinute(),
        ]);

        $response = $this->withHeader('X-Gadget-User-ID', (string) $gadgetUser->uuid)
            ->postJson('/api/auth/login', [
                'email' => 'owner@example.com',
                'password' => 'password123',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $subjectUser->id)
            ->assertJsonPath('gadget_user_id', $gadgetUser->uuid);

        $this->assertSame(1, UserAgent::query()->where('device_identifier', 'gadget-login-1')->count());
        $this->assertSame(
            $gadgetUser->id,
            UserAgent::query()->where('device_identifier', 'gadget-login-1')->value('user_id'),
        );
    }
}
