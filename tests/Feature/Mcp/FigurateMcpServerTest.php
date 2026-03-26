<?php

namespace Tests\Feature\Mcp;

use App\Ai\Gateways\Mcp\Prompts\PlanSpaceWorkPrompt;
use App\Ai\Gateways\Mcp\Resources\FigurateServerGuideResource;
use App\Ai\Gateways\Mcp\Servers\FigurateServer;
use App\Ai\Gateways\Mcp\Tools\AssignThreadActorTool;
use App\Ai\Gateways\Mcp\Tools\CreateGraphEdgeTool;
use App\Ai\Gateways\Mcp\Tools\CreatePostTool;
use App\Ai\Gateways\Mcp\Tools\CreateThreadTool;
use App\Ai\Gateways\Mcp\Tools\ListSpacesTool;
use App\Ai\Gateways\Mcp\Tools\QueryGraphEdgesTool;
use App\Ai\Gateways\Mcp\Tools\ReadThreadTool;
use App\Ai\Gateways\Mcp\Tools\SearchConversationContextTool;
use App\Ai\Gateways\Mcp\Tools\TransferThreadSessionTool;
use App\Models\Server\AgentConversation;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\SpaceRelation;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\ThreadActorSession;
use App\Models\Server\ThreadRelation;
use App\Models\Server\User;
use Database\Factories\SpaceFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FigurateMcpServerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_accessible_spaces(): void
    {
        $user = $this->makeUser(User::TypeRobot);
        $visibleSpace = $this->accessibleSpace($user);
        SpaceFactory::new()->create();

        $response = FigurateServer::actingAs($user)->tool(ListSpacesTool::class, [
            'limit' => 10,
        ]);

        $response->assertOk()->assertSee($visibleSpace->uuid);
        $response->assertDontSee('"count":0');
    }

    public function test_it_reads_a_thread_with_messages_posts_and_actors(): void
    {
        $user = $this->makeUser();
        $space = $this->accessibleSpace($user);
        $thread = $space->threads()->create([
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
        $message = Post::query()->create([
            'postable_type' => $thread->getMorphClass(),
            'postable_id' => $thread->id,
            'type' => Post::TypeMessage,
            'status' => Post::StatusActive,
            'text' => 'Need a quick check on the kitchen sink leak.',
            'meta' => ['source' => 'test'],
        ]);
        $message->attachRelation($user, Post::RelationRoleSender);
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
        $space = $this->accessibleSpace($user);

        $createThread = FigurateServer::actingAs($user)->tool(CreateThreadTool::class, [
            'space_id' => $space->uuid,
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

    public function test_it_creates_graph_edges_via_mcp_tools(): void
    {
        $user = $this->makeUser();
        $sourceSpace = $this->accessibleSpace($user);
        $targetSpace = $this->accessibleSpace($user);

        $response = FigurateServer::actingAs($user)->tool(CreateGraphEdgeTool::class, [
            'source_type' => 'space',
            'source_id' => $sourceSpace->uuid,
            'target_type' => 'space',
            'target_id' => $targetSpace->uuid,
            'edge_type' => SpaceRelation::TypeDependsOn,
            'purpose' => 'Need target space as an upstream dependency.',
        ]);

        $response->assertOk()
            ->assertSee(SpaceRelation::TypeDependsOn)
            ->assertSee($sourceSpace->uuid)
            ->assertSee($targetSpace->uuid);

        $this->assertDatabaseHas('space_relations', [
            'space_id' => $sourceSpace->id,
            'relationable_type' => $targetSpace->getMorphClass(),
            'relationable_id' => $targetSpace->id,
            'type' => SpaceRelation::TypeDependsOn,
        ]);
    }

    public function test_it_searches_thread_context(): void
    {
        $user = $this->makeUser();
        $space = $this->accessibleSpace($user);
        $thread = $space->threads()->create([
            'title' => 'Drain issue',
            'purpose' => Thread::PurposeSupport,
            'phase' => 'support_open',
            'status' => 'open',
        ]);
        $message = Post::query()->create([
            'postable_type' => $thread->getMorphClass(),
            'postable_id' => $thread->id,
            'type' => Post::TypeMessage,
            'status' => Post::StatusActive,
            'text' => 'The basement drain smells bad after the rain.',
            'meta' => ['source' => 'test'],
        ]);
        $message->attachRelation($user, Post::RelationRoleSender);
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

    public function test_it_queries_graph_edges_with_depth(): void
    {
        $user = $this->makeUser();
        $sourceSpace = $this->accessibleSpace($user);
        $dependencySpace = $this->accessibleSpace($user);
        $knowledgeSpace = $this->accessibleSpace($user);
        $sourceThread = $sourceSpace->threads()->create([
            'title' => 'Source thread',
            'purpose' => Thread::PurposePlanning,
            'phase' => 'scope_planning',
            'status' => 'open',
        ]);
        $dependencyThread = $dependencySpace->threads()->create([
            'title' => 'Dependency thread',
            'purpose' => Thread::PurposeExecution,
            'phase' => 'order_kickoff',
            'status' => 'open',
        ]);
        $knowledgeThread = $knowledgeSpace->threads()->create([
            'title' => 'Knowledge thread',
            'purpose' => Thread::PurposeSupport,
            'phase' => 'support_open',
            'status' => 'open',
        ]);

        $sourceSpace->attachRelation($dependencySpace, SpaceRelation::TypeDependsOn, 'Depends on fulfillment context');
        $dependencySpace->attachRelation($knowledgeSpace, SpaceRelation::TypeReferences, 'Needs supporting knowledge context');
        $dependencyThread->attachRelation($knowledgeThread, ThreadRelation::TypeReferences, 'See related thread');

        $response = FigurateServer::actingAs($user)->tool(QueryGraphEdgesTool::class, [
            'node_type' => 'space',
            'node_id' => $sourceSpace->uuid,
            'direction' => 'outgoing',
            'depth' => 3,
        ]);

        $response->assertOk()
            ->assertSee($sourceSpace->uuid)
            ->assertSee($dependencySpace->uuid)
            ->assertSee($knowledgeSpace->uuid)
            ->assertSee(ThreadRelation::TypeReferences)
            ->assertSee('edge_count');

        $this->assertEqualsCanonicalizing(
            [$sourceThread->id, $dependencyThread->id, $knowledgeThread->id],
            $sourceSpace->conversationThreadIds()->all(),
        );
    }

    public function test_it_exposes_a_guide_resource_and_channel_planning_prompt(): void
    {
        $user = $this->makeUser();
        $space = $this->accessibleSpace($user);

        FigurateServer::actingAs($user)
            ->resource(FigurateServerGuideResource::class)
            ->assertOk()
            ->assertSee('Figurate MCP Guide')
            ->assertSee('create_thread');

        FigurateServer::actingAs($user)
            ->prompt(PlanSpaceWorkPrompt::class, [
                'space_id' => $space->uuid,
                'objective' => 'Prepare the next repair workflow.',
            ])
            ->assertOk()
            ->assertSee($space->uuid)
            ->assertSee('Prepare the next repair workflow');
    }

    public function test_it_assigns_thread_actors_and_transfers_thread_sessions(): void
    {
        $user = $this->makeUser();
        $targetUser = $this->makeUser();
        $space = $this->accessibleSpace($user);
        $thread = $space->threads()->create([
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

    protected function accessibleSpace(User $user): Space
    {
        $space = SpaceFactory::new()->create();

        SpaceActorState::query()->create([
            'space_id' => $space->id,
            'thread_id' => null,
            'actorable_type' => $user->getMorphClass(),
            'actorable_id' => $user->id,
            'status' => SpaceActorState::StatusActive,
        ]);

        return $space;
    }

    protected function makeUser(string $type = User::TypeSubject): User
    {
        return User::query()->create([
            'name' => 'MCP Tester',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'type' => $type,
            'status' => 'active',
        ]);
    }
}
