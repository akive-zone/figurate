<?php

namespace Tests\Feature\Graph;

use App\Models\Server\SanctumUser;
use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\SpaceRelation;
use App\Models\Server\User;
use App\TokenAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GraphQLApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_graphql_route_requires_authentication(): void
    {
        $this->postJson('/api/graphql', [
            'query' => 'query { graphNode(type: SPACE, id: "missing") { id } }',
        ])->assertUnauthorized();
    }

    public function test_it_resolves_an_authorized_graph_node(): void
    {
        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);

        Sanctum::actingAs($user);

        $this->postJson('/api/graphql', [
            'query' => <<<'GRAPHQL'
                query GraphNode($id: ID!) {
                    graphNode(type: SPACE, id: $id) {
                        type
                        id
                        attributes {
                            status
                            threadCount
                            postCount
                        }
                    }
                }
                GRAPHQL,
            'variables' => ['id' => $space->uuid],
        ])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.graphNode.type', 'SPACE')
            ->assertJsonPath('data.graphNode.id', $space->uuid)
            ->assertJsonPath('data.graphNode.attributes.status', 'open')
            ->assertJsonPath('data.graphNode.attributes.threadCount', 0);
    }

    public function test_it_traverses_graph_edges_from_a_root_node(): void
    {
        $user = User::factory()->create();
        $root = $this->accessibleSpace($user);
        $target = $this->accessibleSpace($user);
        $relation = $root->attachRelation(
            $target,
            SpaceRelation::TypeDependsOn,
            'The root depends on the target.',
        );

        Sanctum::actingAs($user);

        $this->postJson('/api/graphql', [
            'query' => <<<'GRAPHQL'
                query GraphEdges($nodeId: ID!) {
                    graphEdges(nodeType: SPACE, nodeId: $nodeId, direction: OUTGOING) {
                        root { id type }
                        nodes { id type }
                        edges {
                            id
                            type
                            direction
                            purpose
                            source { id }
                            target { id }
                        }
                        meta {
                            edgeCount
                            nodeCount
                            depth
                            direction
                        }
                    }
                }
                GRAPHQL,
            'variables' => ['nodeId' => $root->uuid],
        ])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.graphEdges.root.id', $root->uuid)
            ->assertJsonPath('data.graphEdges.edges.0.id', $relation->ulid)
            ->assertJsonPath('data.graphEdges.edges.0.type', SpaceRelation::TypeDependsOn)
            ->assertJsonPath('data.graphEdges.edges.0.direction', 'OUTGOING')
            ->assertJsonPath('data.graphEdges.edges.0.target.id', $target->uuid)
            ->assertJsonPath('data.graphEdges.meta.edgeCount', 1)
            ->assertJsonPath('data.graphEdges.meta.nodeCount', 2);
    }

    public function test_graphql_queries_enforce_their_matching_api_abilities(): void
    {
        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);
        $token = SanctumUser::query()
            ->findOrFail($user->id)
            ->createToken('api:graphql-reader', [TokenAbility::NodesRead->value])
            ->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}");

        $this->postJson('/api/graphql', [
            'query' => 'query($id: ID!) { graphNode(type: SPACE, id: $id) { id } }',
            'variables' => ['id' => $space->uuid],
        ])
            ->assertOk()
            ->assertJsonPath('data.graphNode.id', $space->uuid);

        $this->postJson('/api/graphql', [
            'query' => 'query($id: ID!) { graphEdges(nodeType: SPACE, nodeId: $id) { root { id } } }',
            'variables' => ['id' => $space->uuid],
        ])
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath(
                'errors.0.message',
                'The API credential does not have the required edges:read ability.',
            );
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
}
