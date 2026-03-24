<?php

namespace Figurate\AccountManager\Tests;

use App\Models\Server\AgentConversation;
use App\Models\Server\AgentTask;
use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\ThreadActorSession;
use App\Models\Server\User;
use App\Models\Server\UserAgent;
use Figurate\AccountManager\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetAccountAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_ensures_primary_account_via_registered_event_without_creating_a_widget_user(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Studio Owner',
            'email' => 'owner-event@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.type', User::TypeSubject)
            ->assertJsonPath('user.email', 'owner-event@example.com');

        $account = Account::query()->where('name', 'Studio Owner')->firstOrFail();
        $subjectUser = User::query()->where('email', 'owner-event@example.com')->firstOrFail();

        $this->assertDatabaseHas('account_users', [
            'account_id' => $account->id,
            'user_id' => $subjectUser->id,
            'type' => 'owner',
            'is_primary' => true,
            'unlinked_at' => null,
        ]);

        $this->assertDatabaseMissing('account_users', [
            'account_id' => $account->id,
            'type' => 'widget',
            'unlinked_at' => null,
        ]);
    }

    public function test_register_links_an_existing_widget_user_to_the_new_subject_account(): void
    {
        $widgetUser = $this->makeUser(User::TypeWidget, 'machine-register-event-1');

        $response = $this->withHeader('X-Widget-User-ID', (string) $widgetUser->uuid)
            ->postJson('/api/auth/register', [
                'name' => 'Studio Owner',
                'email' => 'owner-link@example.com',
                'password' => 'password123',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.type', User::TypeSubject)
            ->assertJsonPath('user.email', 'owner-link@example.com');

        $account = Account::query()->where('name', 'Studio Owner')->firstOrFail();
        $subjectUser = User::query()->where('email', 'owner-link@example.com')->firstOrFail();

        $this->assertDatabaseHas('account_users', [
            'account_id' => $account->id,
            'user_id' => $subjectUser->id,
            'type' => 'owner',
            'is_primary' => true,
            'unlinked_at' => null,
        ]);

        $this->assertDatabaseHas('account_users', [
            'account_id' => $account->id,
            'user_id' => $widgetUser->id,
            'type' => 'widget',
            'unlinked_at' => null,
        ]);
    }

    public function test_register_creates_an_account_without_creating_a_new_widget_user(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Studio Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.type', User::TypeSubject)
            ->assertJsonPath('user.email', 'owner@example.com');

        $token = (string) $response->json('token');
        $this->withToken($token)
            ->getJson('/api/accounts/current')
            ->assertOk()
            ->assertJsonPath('data.name', 'Studio Owner');

        $account = Account::query()->where('name', 'Studio Owner')->firstOrFail();
        $subjectUser = User::query()->where('email', 'owner@example.com')->firstOrFail();

        $this->assertSame(User::TypeSubject, $subjectUser->type);

        $this->assertDatabaseHas('account_users', [
            'account_id' => $account->id,
            'user_id' => $subjectUser->id,
            'type' => 'owner',
            'is_primary' => true,
            'unlinked_at' => null,
        ]);

        $this->assertDatabaseMissing('account_users', [
            'account_id' => $account->id,
            'type' => 'widget',
            'unlinked_at' => null,
        ]);

        $this->assertDatabaseCount('user_agents', 0);
    }

    public function test_login_attaches_existing_widget_via_login_event(): void
    {
        $account = Account::query()->create([
            'name' => 'Existing Owner',
            'status' => 'active',
        ]);
        $subjectUser = User::query()->create([
            'name' => 'Existing Owner',
            'email' => 'existing-event@example.com',
            'password' => 'password123',
            'type' => User::TypeSubject,
            'status' => 'active',
        ]);
        $account->users()->attach($subjectUser->id, [
            'type' => 'owner',
            'is_primary' => true,
            'linked_at' => now(),
        ]);

        $widgetUser = $this->makeUser(User::TypeWidget, 'widget-login-event-1');

        $response = $this->withHeader('X-Widget-User-ID', (string) $widgetUser->uuid)
            ->postJson('/api/auth/login', [
                'email' => 'existing-event@example.com',
                'password' => 'password123',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $subjectUser->id);

        $this->assertDatabaseHas('account_users', [
            'account_id' => $account->id,
            'user_id' => $widgetUser->id,
            'type' => 'widget',
            'unlinked_at' => null,
        ]);
    }

    public function test_login_attaches_existing_widget_without_rewriting_actor_scoped_resources(): void
    {
        $account = Account::query()->create([
            'name' => 'Existing Owner',
            'status' => 'active',
        ]);
        $subjectUser = User::query()->create([
            'name' => 'Existing Owner',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'type' => User::TypeSubject,
            'status' => 'active',
        ]);
        $account->users()->attach($subjectUser->id, [
            'type' => 'owner',
            'is_primary' => true,
            'linked_at' => now(),
        ]);

        $widgetUser = $this->makeUser(User::TypeWidget, 'widget-login-1');
        $channel = Channel::query()->create(['status' => 'open']);
        ChannelActorState::query()->create([
            'channel_id' => $channel->id,
            'thread_id' => null,
            'actorable_type' => $widgetUser->getMorphClass(),
            'actorable_id' => $widgetUser->id,
            'status' => ChannelActorState::StatusActive,
        ]);

        $thread = $channel->threads()->create([
            'title' => 'Anonymous planning',
            'purpose' => Thread::PurposePlanning,
            'phase' => 'draft',
            'status' => 'open',
        ]);

        $threadActor = ThreadActor::query()->create([
            'thread_id' => $thread->id,
            'actorable_type' => $widgetUser->getMorphClass(),
            'actorable_id' => $widgetUser->id,
            'role' => ThreadActor::RoleMember,
            'status' => ThreadActor::StatusActive,
            'priority' => 1,
            'config' => null,
        ]);

        $conversation = AgentConversation::query()->create([
            'id' => 'conv-widget-login-1',
            'user_id' => $widgetUser->id,
            'title' => 'Anonymous conversation',
        ]);

        $session = ThreadActorSession::query()->create([
            'thread_id' => $thread->id,
            'thread_actor_id' => $threadActor->id,
            'user_id' => $widgetUser->id,
            'conversation_id' => $conversation->id,
            'provider' => 'openai',
            'model' => 'gpt-test',
        ]);

        $task = AgentTask::query()->create([
            'uuid' => (string) fake()->uuid(),
            'thread_id' => $thread->id,
            'user_id' => $widgetUser->id,
            'status' => 'submitted',
        ]);

        $response = $this->withHeader('X-Widget-User-ID', (string) $widgetUser->uuid)
            ->postJson('/api/auth/login', [
                'email' => 'existing@example.com',
                'password' => 'password123',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $subjectUser->id);

        $token = (string) $response->json('token');
        $this->withToken($token)
            ->getJson('/api/accounts/current')
            ->assertOk()
            ->assertJsonPath('data.id', $account->id);

        $this->assertDatabaseHas('account_users', [
            'account_id' => $account->id,
            'user_id' => $widgetUser->id,
            'type' => 'widget',
            'unlinked_at' => null,
        ]);

        $channel->refresh();
        $thread->refresh();
        $conversation->refresh();
        $session->refresh();
        $task->refresh();

        $this->assertSame($widgetUser->id, $conversation->user_id);
        $this->assertSame($widgetUser->id, $session->user_id);
        $this->assertSame($widgetUser->id, $task->user_id);
        $this->assertTrue($channel->hasActor($widgetUser));
        $this->assertDatabaseHas('thread_actors', [
            'id' => $threadActor->id,
            'actorable_type' => $widgetUser->getMorphClass(),
            'actorable_id' => $widgetUser->id,
        ]);
    }

    protected function makeUser(string $type, string $deviceIdentifier): User
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'type' => $type,
            'status' => 'active',
        ]);

        UserAgent::query()->create([
            'user_id' => $user->id,
            'kind' => 'api',
            'device_identifier' => $deviceIdentifier,
            'last_seen_at' => now(),
        ]);

        return $user;
    }
}
