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

    public function test_it_creates_updates_and_deletes_graph_nodes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/graphql', [
            'query' => <<<'GRAPHQL'
                mutation CreateGraphNode($input: CreateGraphNodeInput!) {
                    createGraphNode(input: $input) {
                        id
                        type
                        attributes { status }
                    }
                }
                GRAPHQL,
            'variables' => [
                'input' => [
                    'type' => 'SPACE',
                    'attributes' => ['status' => 'draft'],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.createGraphNode.type', 'SPACE')
            ->assertJsonPath('data.createGraphNode.attributes.status', 'draft');

        $nodeId = (string) $created->json('data.createGraphNode.id');
        $this->assertDatabaseHas('spaces', ['uuid' => $nodeId, 'status' => 'draft']);

        $this->postJson('/api/graphql', [
            'query' => <<<'GRAPHQL'
                mutation UpdateGraphNode($input: UpdateGraphNodeInput!) {
                    updateGraphNode(input: $input) {
                        id
                        attributes { status }
                    }
                }
                GRAPHQL,
            'variables' => [
                'input' => [
                    'type' => 'SPACE',
                    'id' => $nodeId,
                    'attributes' => ['status' => 'active'],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.updateGraphNode.id', $nodeId)
            ->assertJsonPath('data.updateGraphNode.attributes.status', 'active');

        $this->postJson('/api/graphql', [
            'query' => <<<'GRAPHQL'
                mutation DeleteGraphNode($type: GraphNodeType!, $id: ID!) {
                    deleteGraphNode(type: $type, id: $id) { id type }
                }
                GRAPHQL,
            'variables' => ['type' => 'SPACE', 'id' => $nodeId],
        ])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.deleteGraphNode.id', $nodeId)
            ->assertJsonPath('data.deleteGraphNode.type', 'SPACE');

        $this->assertSoftDeleted('spaces', ['uuid' => $nodeId]);
    }

    public function test_it_creates_updates_and_deletes_graph_edges(): void
    {
        $user = User::factory()->create();
        $source = $this->accessibleSpace($user);
        $target = $this->accessibleSpace($user);
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/graphql', [
            'query' => <<<'GRAPHQL'
                mutation CreateGraphEdge($input: CreateGraphEdgeInput!) {
                    createGraphEdge(input: $input) {
                        id
                        type
                        purpose
                        source { id }
                        target { id }
                    }
                }
                GRAPHQL,
            'variables' => [
                'input' => [
                    'source' => ['type' => 'SPACE', 'id' => $source->uuid],
                    'target' => ['type' => 'SPACE', 'id' => $target->uuid],
                    'edgeType' => 'references',
                    'purpose' => 'Initial reference',
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.createGraphEdge.type', 'references')
            ->assertJsonPath('data.createGraphEdge.source.id', $source->uuid)
            ->assertJsonPath('data.createGraphEdge.target.id', $target->uuid);

        $edgeId = (string) $created->json('data.createGraphEdge.id');

        $this->postJson('/api/graphql', [
            'query' => <<<'GRAPHQL'
                mutation UpdateGraphEdge($input: UpdateGraphEdgeInput!) {
                    updateGraphEdge(input: $input) { id type purpose }
                }
                GRAPHQL,
            'variables' => [
                'input' => [
                    'id' => $edgeId,
                    'edgeType' => 'supports',
                    'purpose' => 'Updated reference',
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.updateGraphEdge.id', $edgeId)
            ->assertJsonPath('data.updateGraphEdge.type', 'supports')
            ->assertJsonPath('data.updateGraphEdge.purpose', 'Updated reference');

        $this->postJson('/api/graphql', [
            'query' => 'mutation($id: ID!) { deleteGraphEdge(id: $id) { id } }',
            'variables' => ['id' => $edgeId],
        ])
            ->assertOk()
            ->assertJsonMissingPath('errors')
            ->assertJsonPath('data.deleteGraphEdge.id', $edgeId);

        $this->assertDatabaseMissing('space_relations', ['ulid' => $edgeId]);
    }

    public function test_graphql_mutations_validate_inputs_and_write_abilities(): void
    {
        $user = User::factory()->create();
        $token = SanctumUser::query()
            ->findOrFail($user->id)
            ->createToken('api:graphql-reader', [TokenAbility::NodesRead->value])
            ->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}");

        $this->postJson('/api/graphql', [
            'query' => <<<'GRAPHQL'
                mutation {
                    createGraphNode(input: { type: SPACE }) { id }
                }
                GRAPHQL,
        ])
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath(
                'errors.0.message',
                'The API credential does not have the required nodes:write ability.',
            );

        Sanctum::actingAs($user);

        $this->postJson('/api/graphql', [
            'query' => <<<'GRAPHQL'
                mutation {
                    createGraphNode(input: { type: THREAD, attributes: { title: "Orphan" } }) { id }
                }
                GRAPHQL,
        ])
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonFragment([
                'input.parent.id' => ['A parent node is required.'],
            ]);
    }

    public function test_graphql_mutations_protect_child_nodes_and_reserved_edges(): void
    {
        $user = User::factory()->create();
        $parent = $this->accessibleSpace($user);
        $child = $this->accessibleSpace($user);
        $child->attachRelation($parent, SpaceRelation::TypeChildOf);
        Sanctum::actingAs($user);

        $this->postJson('/api/graphql', [
            'query' => 'mutation($id: ID!) { deleteGraphNode(type: SPACE, id: $id) { id } }',
            'variables' => ['id' => $parent->uuid],
        ])
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath(
                'errors.0.message',
                'A node with child nodes cannot be deleted.',
            );

        $this->postJson('/api/graphql', [
            'query' => <<<'GRAPHQL'
                mutation CreateReservedEdge($input: CreateGraphEdgeInput!) {
                    createGraphEdge(input: $input) { id }
                }
                GRAPHQL,
            'variables' => [
                'input' => [
                    'source' => ['type' => 'SPACE', 'id' => $child->uuid],
                    'target' => ['type' => 'SPACE', 'id' => $parent->uuid],
                    'edgeType' => SpaceRelation::TypeChildOf,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonFragment([
                'input.edgeType' => ['The edge type is not supported.'],
            ]);
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
