<?php

namespace Tests\Feature\Auth;

use App\Events\Server\Auth\SubjectAuthenticated;
use App\Models\Server\User;
use App\Models\Server\UserClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_can_link_an_existing_widget_user_without_creating_a_new_one(): void
    {
        Event::fake([SubjectAuthenticated::class]);

        $widgetUser = User::query()->create([
            'name' => 'Existing Widget',
            'email' => 'widget-register-1@example.invalid',
            'password' => 'password123',
            'type' => User::TypeWidget,
            'status' => 'active',
        ]);

        UserClient::query()->create([
            'user_id' => $widgetUser->id,
            'kind' => 'api',
            'device_identifier' => 'machine-register-1',
            'last_seen_at' => now()->subMinute(),
        ]);

        $response = $this->withHeader('X-Widget-User-ID', (string) $widgetUser->uuid)
            ->postJson('/api/auth/register', [
                'name' => 'Studio Owner',
                'email' => 'owner@example.com',
                'password' => 'password123',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.type', User::TypeSubject);

        Event::assertDispatched(SubjectAuthenticated::class, function (SubjectAuthenticated $event) use ($widgetUser): bool {
            return $event->user->isSubject()
                && $event->action === 'register'
                && data_get($event->widgetUserContext, 'headers.X-Widget-User-ID') === $widgetUser->uuid;
        });

        $this->assertDatabaseHas('user_clients', [
            'user_id' => $widgetUser->id,
            'device_identifier' => 'machine-register-1',
        ]);

        $this->assertSame(1, User::query()->where('type', User::TypeWidget)->count());
        $this->assertDatabaseMissing('users', [
            'email' => 'widget-owner@example.com',
        ]);
    }

    public function test_login_resolves_an_existing_widget_user_without_creating_a_new_one(): void
    {
        Event::fake([SubjectAuthenticated::class]);

        $subjectUser = User::query()->create([
            'name' => 'Studio Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'type' => User::TypeSubject,
            'status' => 'active',
        ]);
        $widgetUser = User::query()->create([
            'name' => 'Existing Widget',
            'email' => 'widget-login-1@example.invalid',
            'password' => 'password123',
            'type' => User::TypeWidget,
            'status' => 'active',
        ]);

        UserClient::query()->create([
            'user_id' => $widgetUser->id,
            'kind' => 'api',
            'device_identifier' => 'widget-login-1',
            'last_seen_at' => now()->subMinute(),
        ]);

        $response = $this->withHeader('X-Widget-User-ID', (string) $widgetUser->uuid)
            ->postJson('/api/auth/login', [
                'email' => 'owner@example.com',
                'password' => 'password123',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $subjectUser->id);

        Event::assertDispatched(SubjectAuthenticated::class, function (SubjectAuthenticated $event) use ($subjectUser, $widgetUser): bool {
            return $event->user->is($subjectUser)
                && $event->action === 'login'
                && data_get($event->widgetUserContext, 'headers.X-Widget-User-ID') === $widgetUser->uuid;
        });

        $this->assertSame(1, UserClient::query()->where('device_identifier', 'widget-login-1')->count());
        $this->assertSame(
            $widgetUser->id,
            UserClient::query()->where('device_identifier', 'widget-login-1')->value('user_id'),
        );
    }
}
