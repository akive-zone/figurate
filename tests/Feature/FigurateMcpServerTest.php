<?php

namespace Tests\Feature;

use App\Ai\Gateways\Mcp\Prompts\PlanChannelWorkPrompt;
use App\Ai\Gateways\Mcp\Resources\FigurateServerGuideResource;
use App\Ai\Gateways\Mcp\Servers\FigurateServer;
use App\Ai\Gateways\Mcp\Tools\AssignThreadActorTool;
use App\Ai\Gateways\Mcp\Tools\CreatePostTool;
use App\Ai\Gateways\Mcp\Tools\CreateThreadTool;
use App\Ai\Gateways\Mcp\Tools\ListChannelsTool;
use App\Ai\Gateways\Mcp\Tools\ReadThreadTool;
use App\Ai\Gateways\Mcp\Tools\SearchConversationContextTool;
use App\Ai\Gateways\Mcp\Tools\TransferThreadSessionTool;
use App\Models\Server\AgentConversation;
use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Message;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\ThreadActorSession;
use App\Models\Server\User;
use Database\Factories\ChannelFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FigurateMcpServerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_accessible_channels(): void
    {
        $user = $this->makeUser();
        $visibleChannel = $this->accessibleChannel($user);
        ChannelFactory::new()->create();

        $response = FigurateServer::actingAs($user)->tool(ListChannelsTool::class, [
            'limit' => 10,
        ]);

        $response->assertOk()->assertSee($visibleChannel->uuid);
        $response->assertDontSee('"count":0');
    }

    public function test_it_reads_a_thread_with_messages_posts_and_actors(): void
    {
        $user = $this->makeUser();
        $channel = $this->accessibleChannel($user);
        $thread = $channel->threads()->create([
            'title' => 'Repair scope',
            'purpose' => Thread::PurposePlanning,
            'phase' => 'scope_planning',
            'status' => 'open',
        ]);
        $thread->actors()->create([
            'actorable_type' => $user->getMorphClass(),
            'actorable_id' => $user->getKey(),
            'role' => ThreadActor::RoleMember,
            'status' => ThreadActor::StatusActive,
            'priority' => 1,
            'config' => null,
        ]);
        Message::query()->create([
            'messageable_type' => $thread->getMorphClass(),
            'messageable_id' => $thread->id,
            'senderable_type' => $user->getMorphClass(),
            'senderable_id' => $user->getKey(),
            'type' => 'text',
            'text' => 'Need a quick check on the kitchen sink leak.',
            'meta' => ['source' => 'test'],
        ]);
        $thread->posts()->create([
            'type' => 'summary.snapshot',
            'status' => 'draft',
            'payload' => ['title' => 'Scope summary'],
            'meta' => ['source' => 'test'],
            'occurred_at' => now(),
        ]);

        $response = FigurateServer::actingAs($user)->tool(ReadThreadTool::class, [
            'thread_id' => $thread->uuid,
        ]);

        $response->assertOk()
            ->assertSee($thread->uuid)
            ->assertSee('kitchen sink leak')
            ->assertSee('summary.snapshot');
    }

    public function test_it_creates_threads_and_posts_via_mcp_tools(): void
    {
        $user = $this->makeUser();
        $channel = $this->accessibleChannel($user);

        $createThread = FigurateServer::actingAs($user)->tool(CreateThreadTool::class, [
            'channel_id' => $channel->uuid,
            'title' => 'Source replacement',
            'purpose' => Thread::PurposeExecution,
        ]);

        $createThread->assertOk()->assertSee('Source replacement');

        $thread = Thread::query()->where('title', 'Source replacement')->firstOrFail();

        $createPost = FigurateServer::actingAs($user)->tool(CreatePostTool::class, [
            'target_type' => 'thread',
            'target_id' => $thread->uuid,
            'type' => 'note.created',
            'title' => 'Inspection note',
            'body' => 'Technician should bring replacement hose.',
        ]);

        $createPost->assertOk()
            ->assertSee('note.created')
            ->assertSee('Inspection note');

        $this->assertDatabaseHas('posts', [
            'postable_type' => $thread->getMorphClass(),
            'postable_id' => $thread->id,
            'type' => 'note.created',
        ]);
    }

    public function test_it_searches_thread_context(): void
    {
        $user = $this->makeUser();
        $channel = $this->accessibleChannel($user);
        $thread = $channel->threads()->create([
            'title' => 'Drain issue',
            'purpose' => Thread::PurposeSupport,
            'phase' => 'support_open',
            'status' => 'open',
        ]);
        Message::query()->create([
            'messageable_type' => $thread->getMorphClass(),
            'messageable_id' => $thread->id,
            'senderable_type' => $user->getMorphClass(),
            'senderable_id' => $user->getKey(),
            'type' => 'text',
            'text' => 'The basement drain smells bad after the rain.',
            'meta' => ['source' => 'test'],
        ]);
        Post::query()->create([
            'postable_type' => $thread->getMorphClass(),
            'postable_id' => $thread->id,
            'type' => 'summary.snapshot',
            'status' => 'draft',
            'payload' => ['body' => 'Drain odor likely from trapped debris.'],
            'meta' => ['source' => 'test'],
            'occurred_at' => now(),
        ]);

        $response = FigurateServer::actingAs($user)->tool(SearchConversationContextTool::class, [
            'thread_id' => $thread->uuid,
            'query' => 'drain',
        ]);

        $response->assertOk()
            ->assertSee('basement drain smells bad')
            ->assertSee('Drain odor likely from trapped debris');
    }

    public function test_it_exposes_a_guide_resource_and_channel_planning_prompt(): void
    {
        $user = $this->makeUser();
        $channel = $this->accessibleChannel($user);

        FigurateServer::actingAs($user)
            ->resource(FigurateServerGuideResource::class)
            ->assertOk()
            ->assertSee('Figurate MCP Guide')
            ->assertSee('create_thread');

        FigurateServer::actingAs($user)
            ->prompt(PlanChannelWorkPrompt::class, [
                'channel_id' => $channel->uuid,
                'objective' => 'Prepare the next repair workflow.',
            ])
            ->assertOk()
            ->assertSee($channel->uuid)
            ->assertSee('Prepare the next repair workflow');
    }

    public function test_it_assigns_thread_actors_and_transfers_thread_sessions(): void
    {
        $user = $this->makeUser();
        $targetUser = $this->makeUser();
        $channel = $this->accessibleChannel($user);
        $thread = $channel->threads()->create([
            'title' => 'Assigned work',
            'purpose' => Thread::PurposeExecution,
            'phase' => 'order_kickoff',
            'status' => 'open',
        ]);

        $assignResponse = FigurateServer::actingAs($user)->tool(AssignThreadActorTool::class, [
            'thread_id' => $thread->uuid,
            'actor_type' => 'named',
            'actor_key' => ThreadActor::ActorOrderAgent,
            'role' => ThreadActor::RolePresenter,
            'status' => ThreadActor::StatusActive,
        ]);

        $assignResponse->assertOk()->assertSee(ThreadActor::ActorOrderAgent);

        $threadActor = ThreadActor::query()
            ->where('thread_id', $thread->id)
            ->where('actorable_type', ThreadActor::ActorOrderAgent)
            ->where('role', ThreadActor::RolePresenter)
            ->firstOrFail();

        $conversation = new AgentConversation;
        $conversation->forceFill([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'title' => 'Transfer source session',
        ])->save();

        $fromSession = ThreadActorSession::query()->create([
            'thread_id' => $thread->id,
            'thread_actor_id' => $threadActor->id,
            'user_id' => $user->id,
            'provider' => 'openai',
            'model' => 'gpt-test',
            'conversation_id' => $conversation->id,
            'state' => null,
            'last_used_at' => now(),
        ]);

        $transferResponse = FigurateServer::actingAs($user)->tool(TransferThreadSessionTool::class, [
            'thread_id' => $thread->uuid,
            'from_user_id' => $user->id,
            'to_user_id' => $targetUser->id,
            'thread_actor_id' => $threadActor->id,
        ]);

        $transferResponse->assertOk()
            ->assertSee((string) $fromSession->id)
            ->assertSee($conversation->id);

        $this->assertDatabaseHas('thread_actor_sessions', [
            'thread_id' => $thread->id,
            'thread_actor_id' => $threadActor->id,
            'user_id' => $targetUser->id,
            'conversation_id' => $conversation->id,
        ]);
    }

    protected function accessibleChannel(User $user): Channel
    {
        $channel = ChannelFactory::new()->create();

        ChannelActorState::query()->create([
            'channel_id' => $channel->id,
            'thread_id' => null,
            'actorable_type' => $user->getMorphClass(),
            'actorable_id' => $user->id,
            'status' => ChannelActorState::StatusActive,
        ]);

        return $channel;
    }

    protected function makeUser(): User
    {
        return User::query()->create([
            'name' => 'MCP Tester',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'type' => 'person',
            'provider' => null,
            'provider_id' => null,
            'status' => 'active',
        ]);
    }
}
