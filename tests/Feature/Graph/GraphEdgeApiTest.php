<?php

namespace Tests\Feature\Graph;

use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\SpaceRelation;
use App\Models\Server\Thread;
use App\Models\Server\ThreadRelation;
use App\Models\Server\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GraphEdgeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_graph_edge_via_http_api(): void
    {
        $user = User::factory()->create();
        $sourceSpace = $this->accessibleSpace($user);
        $targetThread = $this->accessibleThread($user, 'Target thread');

        Sanctum::actingAs($user);

        $this->postJson('/api/graph/edges', [
            'source_type' => 'space',
            'source_id' => $sourceSpace->uuid,
            'target_type' => 'thread',
            'target_id' => $targetThread->uuid,
            'edge_type' => SpaceRelation::TypeReferences,
            'purpose' => 'Space should reference this thread.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', SpaceRelation::TypeReferences)
            ->assertJsonPath('data.source.id', $sourceSpace->uuid)
            ->assertJsonPath('data.target.id', $targetThread->uuid);

        $this->assertDatabaseHas('space_relations', [
            'space_id' => $sourceSpace->id,
            'relationable_type' => $targetThread->getMorphClass(),
            'relationable_id' => $targetThread->id,
            'type' => SpaceRelation::TypeReferences,
        ]);
    }

    public function test_it_queries_graph_edges_via_http_api(): void
    {
        $user = User::factory()->create();
        $rootSpace = $this->accessibleSpace($user);
        $linkedSpace = $this->accessibleSpace($user);
        $linkedThread = $this->accessibleThread($user, 'Linked thread');
        $linkedPost = $this->accessiblePost($user, $linkedThread, 'Linked post');
        $rootThread = $rootSpace->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Root thread',
            'phase' => 'root_open',
            'status' => 'open',
        ]);

        $rootSpace->attachRelation($linkedSpace, SpaceRelation::TypeDependsOn, 'Needs linked space');
        $linkedSpace->attachRelation($linkedThread, SpaceRelation::TypeReferences, 'Points at linked thread');
        $linkedThread->attachRelation($linkedPost, ThreadRelation::TypeDerivedFrom, 'Carries supporting evidence');
        $rootThread->attachRelation($linkedThread, ThreadRelation::TypeReferences, 'Directly related thread');

        Sanctum::actingAs($user);

        $this->getJson('/api/graph/edges?'.http_build_query([
            'node_type' => 'space',
            'node_id' => $rootSpace->uuid,
            'direction' => 'outgoing',
            'depth' => 3,
        ]))
            ->assertOk()
            ->assertJsonPath('root.id', $rootSpace->uuid)
            ->assertJsonPath('meta.depth', 3)
            ->assertJsonFragment([
                'type' => SpaceRelation::TypeDependsOn,
            ])
            ->assertJsonFragment([
                'type' => SpaceRelation::TypeReferences,
            ])
            ->assertJsonFragment([
                'id' => $linkedThread->uuid,
            ])
            ->assertJsonFragment([
                'type' => 'post',
            ])
            ->assertJsonFragment([
                'id' => $linkedPost->ulid,
            ]);
    }

    public function test_it_creates_post_graph_edges_via_http_api(): void
    {
        $user = User::factory()->create();
        $sourcePost = $this->accessiblePost($user, $this->accessibleThread($user, 'Source thread'), 'Source post');
        $targetThread = $this->accessibleThread($user, 'Target thread');

        Sanctum::actingAs($user);

        $this->postJson('/api/graph/edges', [
            'source_type' => 'post',
            'source_id' => $sourcePost->ulid,
            'target_type' => 'thread',
            'target_id' => $targetThread->uuid,
            'edge_type' => ThreadRelation::TypeReferences,
            'purpose' => 'Post should point to the implementation thread.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', ThreadRelation::TypeReferences)
            ->assertJsonPath('data.source.id', $sourcePost->ulid)
            ->assertJsonPath('data.target.id', $targetThread->uuid)
            ->assertJsonPath('data.purpose', null);

        $this->assertDatabaseHas('post_relations', [
            'post_id' => $sourcePost->id,
            'relationable_type' => $targetThread->getMorphClass(),
            'relationable_id' => $targetThread->id,
            'role' => ThreadRelation::TypeReferences,
        ]);
    }

    public function test_it_forbids_creating_edges_from_inaccessible_source_nodes(): void
    {
        $authorizedUser = User::factory()->create();
        $intruder = User::factory()->create();
        $sourceSpace = $this->accessibleSpace($authorizedUser);
        $targetSpace = $this->accessibleSpace($authorizedUser);

        Sanctum::actingAs($intruder);

        $this->postJson('/api/graph/edges', [
            'source_type' => 'space',
            'source_id' => $sourceSpace->uuid,
            'target_type' => 'space',
            'target_id' => $targetSpace->uuid,
            'edge_type' => SpaceRelation::TypeDependsOn,
        ])->assertForbidden();
    }

    protected function accessibleSpace(User $user): Space
    {
        $space = Space::factory()->create();

        SpaceActorState::query()->create([
            'space_id' => $space->id,
            'thread_id' => null,
            'actorable_type' => $user->getMorphClass(),
            'actorable_id' => $user->id,
            'status' => SpaceActorState::StatusActive,
        ]);

        return $space;
    }

    protected function accessibleThread(User $user, string $title): Thread
    {
        $space = $this->accessibleSpace($user);

        return $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => $title,
            'phase' => 'graph_open',
            'status' => 'open',
        ]);
    }

    protected function accessiblePost(User $user, Thread $thread, string $text): Post
    {
        $post = Post::query()->create([
            'postable_type' => $thread->getMorphClass(),
            'postable_id' => $thread->id,
            'type' => Post::TypeMessage,
            'status' => Post::StatusActive,
            'text' => $text,
            'meta' => ['source' => 'test'],
        ]);

        $post->attachRelation($user, Post::RelationRoleSender);

        return $post;
    }
}
