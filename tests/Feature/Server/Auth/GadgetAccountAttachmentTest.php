<?php

namespace Tests\Feature\Server\Auth;

use App\Models\Server\Account;
use App\Models\Server\AgentConversation;
use App\Models\Server\AgentTask;
use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\ThreadActorSession;
use App\Models\Server\User;
use App\Models\Server\UserAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GadgetAccountAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_an_account_and_attaches_the_current_gadget_user(): void
    {
        $response = $this->withHeader('X-Device-Id', 'gadget-register-1')
            ->postJson('/api/auth/register', [
                'name' => 'Studio Owner',
                'email' => 'owner@example.com',
                'password' => 'password123',
            ]);

        $response->assertOk()
            ->assertJsonPath('account.email', 'owner@example.com')
            ->assertJsonPath('user.type', User::TypeGadget)
            ->assertJsonPath('device_id', 'gadget-register-1');

        $account = Account::query()->where('email', 'owner@example.com')->firstOrFail();
        $userAgent = UserAgent::query()->where('device_identifier', 'gadget-register-1')->firstOrFail();
        $gadgetUser = $userAgent->user()->firstOrFail();

        $this->assertSame(User::TypeGadget, $gadgetUser->type);

        $this->assertDatabaseHas('account_users', [
            'account_id' => $account->id,
            'user_id' => $gadgetUser->id,
            'relationship' => 'gadget',
            'is_primary' => true,
            'unlinked_at' => null,
        ]);

        $this->assertDatabaseHas('user_agents', [
            'user_id' => $gadgetUser->id,
            'device_identifier' => 'gadget-register-1',
        ]);
    }

    public function test_login_attaches_existing_gadget_without_rewriting_actor_scoped_resources(): void
    {
        $account = Account::query()->create([
            'name' => 'Existing Owner',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'status' => 'active',
        ]);

        $gadgetUser = $this->makeUser(User::TypeGadget, 'gadget-login-1');
        $channel = Channel::query()->create(['status' => 'open']);
        ChannelActorState::query()->create([
            'channel_id' => $channel->id,
            'thread_id' => null,
            'actorable_type' => $gadgetUser->getMorphClass(),
            'actorable_id' => $gadgetUser->id,
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
            'actorable_type' => $gadgetUser->getMorphClass(),
            'actorable_id' => $gadgetUser->id,
            'role' => ThreadActor::RoleMember,
            'status' => ThreadActor::StatusActive,
            'priority' => 1,
            'config' => null,
        ]);

        $conversation = AgentConversation::query()->create([
            'id' => 'conv-gadget-login-1',
            'user_id' => $gadgetUser->id,
            'title' => 'Anonymous conversation',
        ]);

        $session = ThreadActorSession::query()->create([
            'thread_id' => $thread->id,
            'thread_actor_id' => $threadActor->id,
            'user_id' => $gadgetUser->id,
            'conversation_id' => $conversation->id,
            'provider' => 'openai',
            'model' => 'gpt-test',
        ]);

        $task = AgentTask::query()->create([
            'uuid' => (string) fake()->uuid(),
            'thread_id' => $thread->id,
            'user_id' => $gadgetUser->id,
            'status' => 'submitted',
        ]);

        $response = $this->withHeader('X-Device-Id', 'gadget-login-1')
            ->postJson('/api/auth/login', [
                'email' => 'existing@example.com',
                'password' => 'password123',
            ]);

        $response->assertOk()
            ->assertJsonPath('account.id', $account->id)
            ->assertJsonPath('user.id', $gadgetUser->id);

        $this->assertDatabaseHas('account_users', [
            'account_id' => $account->id,
            'user_id' => $gadgetUser->id,
            'relationship' => 'gadget',
            'unlinked_at' => null,
        ]);

        $channel->refresh();
        $thread->refresh();
        $conversation->refresh();
        $session->refresh();
        $task->refresh();

        $this->assertSame($gadgetUser->id, $conversation->user_id);
        $this->assertSame($gadgetUser->id, $session->user_id);
        $this->assertSame($gadgetUser->id, $task->user_id);
        $this->assertTrue($channel->hasActor($gadgetUser));
        $this->assertDatabaseHas('thread_actors', [
            'id' => $threadActor->id,
            'actorable_type' => $gadgetUser->getMorphClass(),
            'actorable_id' => $gadgetUser->id,
        ]);
    }

    protected function makeUser(string $type, string $deviceIdentifier): User
    {
        return User::query()->create([
            'name' => 'Test User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'type' => $type,
            'provider' => null,
            'provider_id' => null,
            'status' => 'active',
            'device_identifier' => $deviceIdentifier,
        ]);
    }
}
