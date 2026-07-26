<?php

namespace Tests\Feature;

use App\Models\Server\ApiPersonalAccessToken;
use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\SpaceRelation;
use App\Models\Server\User;
use App\TokenAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ThirdPartyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_issues_lists_and_revokes_scoped_api_credentials(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $created = $this->postJson('/api/credentials', [
            'name' => 'automation',
            'abilities' => [
                TokenAbility::NodesRead->value,
                TokenAbility::NodesWrite->value,
            ],
        ]);

        $created
            ->assertCreated()
            ->assertJsonPath('data.name', 'automation')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.abilities.0', TokenAbility::NodesRead->value)
            ->assertJsonPath('data.abilities.1', TokenAbility::NodesWrite->value);

        $credentialId = (string) $created->json('data.id');
        $this->assertNotSame('', (string) $created->json('data.token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'ulid' => $credentialId,
            'name' => 'api:automation',
        ]);

        $this->getJson('/api/credentials')
            ->assertOk()
            ->assertJsonPath('data.0.id', $credentialId)
            ->assertJsonMissingPath('data.0.token');

        $this->deleteJson("/api/credentials/{$credentialId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'ulid' => $credentialId,
        ]);
    }

    public function test_scoped_credentials_are_enforced_by_the_api(): void
    {
        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);
        Sanctum::actingAs($user);
        $user->withAccessToken(new ApiPersonalAccessToken([
            'name' => 'api:reader',
            'abilities' => [TokenAbility::NodesRead->value],
        ]));

        $this->getJson("/api/spaces/{$space->uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $space->uuid);

        $this->postJson('/api/nodes', [
            'type' => 'space',
            'attributes' => ['status' => 'open'],
        ])->assertForbidden();

        $this->getJson('/api/credentials')
            ->assertForbidden();
    }

    public function test_node_creation_is_idempotent_and_rejects_key_reuse_with_new_input(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, [TokenAbility::NodesWrite->value]);
        $headers = ['Idempotency-Key' => 'create-root-space-42'];

        $first = $this->postJson('/api/nodes', [
            'type' => 'space',
            'attributes' => ['status' => 'preparing'],
        ], $headers)->assertCreated();

        $second = $this->postJson('/api/nodes', [
            'type' => 'space',
            'attributes' => ['status' => 'preparing'],
        ], $headers);

        $second
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertExactJson($first->json());

        $this->assertDatabaseCount('spaces', 1);
        $this->assertDatabaseCount('idempotency_records', 1);

        $this->postJson('/api/nodes', [
            'type' => 'space',
            'attributes' => ['status' => 'different'],
        ], $headers)->assertConflict();
    }

    public function test_nodes_are_cursor_paginated_and_can_be_updated_and_deleted(): void
    {
        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);
        Sanctum::actingAs($user, [
            TokenAbility::NodesRead->value,
            TokenAbility::NodesWrite->value,
        ]);

        foreach (['One', 'Two', 'Three'] as $title) {
            $space->threads()->create([
                'title' => $title,
                'purpose' => 'external',
                'phase' => 'initial',
                'status' => 'open',
            ]);
        }

        $firstPage = $this->getJson("/api/spaces/{$space->uuid}/nodes?per_page=2");
        $firstPage
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.has_more', true);

        $cursor = urlencode((string) $firstPage->json('meta.next_cursor'));
        $this->getJson("/api/spaces/{$space->uuid}/nodes?per_page=2&cursor={$cursor}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.has_more', false);

        $emptySpace = $this->accessibleSpace($user);
        $this->patchJson("/api/nodes/space/{$emptySpace->uuid}", [
            'attributes' => ['status' => 'archived'],
        ])
            ->assertOk()
            ->assertJsonPath('data.attributes.status', 'archived');

        $this->deleteJson("/api/nodes/space/{$emptySpace->uuid}")
            ->assertNoContent();

        $this->assertSoftDeleted('spaces', ['id' => $emptySpace->id]);
    }

    public function test_edges_use_public_ids_and_support_update_and_delete(): void
    {
        $user = User::factory()->create();
        $source = $this->accessibleSpace($user);
        $target = $this->accessibleSpace($user);
        Sanctum::actingAs($user, [
            TokenAbility::EdgesWrite->value,
            TokenAbility::NodesRead->value,
        ]);

        $created = $this->postJson('/api/edges', [
            'source_type' => 'space',
            'source_id' => $source->uuid,
            'target_type' => 'space',
            'target_id' => $target->uuid,
            'edge_type' => SpaceRelation::TypeReferences,
        ]);

        $created
            ->assertCreated()
            ->assertJsonPath('data.source.id', $source->uuid)
            ->assertJsonPath('data.target.id', $target->uuid);

        $edgeId = (string) $created->json('data.id');
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $edgeId);

        $this->patchJson("/api/edges/{$edgeId}", [
            'edge_type' => SpaceRelation::TypeDependsOn,
            'purpose' => 'External dependency',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $edgeId)
            ->assertJsonPath('data.type', SpaceRelation::TypeDependsOn)
            ->assertJsonPath('data.purpose', 'External dependency');

        $this->deleteJson("/api/edges/{$edgeId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('space_relations', ['ulid' => $edgeId]);
    }

    public function test_openapi_document_covers_every_versioned_route(): void
    {
        $document = $this->getJson('/api/openapi.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.1.0')
            ->json();

        $documentedOperations = collect($document['paths'])
            ->flatMap(fn (array $operations): array => array_values(array_filter(
                array_column($operations, 'operationId'),
            )))
            ->all();

        $expectedOperations = collect(Route::getRoutes())
            ->filter(fn ($route): bool => is_string($route->getName()) && str_starts_with($route->getName(), 'api.'))
            ->map(fn ($route): string => str_replace('.', '_', (string) $route->getName()))
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing($expectedOperations, $documentedOperations);
        $this->assertSame(
            TokenAbility::NodesWrite->value,
            $document['paths']['/nodes']['post']['x-required-ability'],
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
