<?php

namespace Tests\Feature\Graph;

use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\SpaceRelation;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GraphNodeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_lists_child_space_nodes(): void
    {
        $user = User::factory()->create();
        $parent = $this->accessibleSpace($user);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/form/nodes', [
            'type' => 'space',
            'parent' => [
                'type' => 'space',
                'id' => $parent->uuid,
            ],
            'attributes' => [
                'status' => 'open',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', 'space');

        $child = Space::query()
            ->where('uuid', $response->json('data.id'))
            ->firstOrFail();

        $this->assertDatabaseHas('space_relations', [
            'space_id' => $child->id,
            'relationable_type' => $parent->getMorphClass(),
            'relationable_id' => $parent->id,
            'type' => SpaceRelation::TypeChildOf,
        ]);

        $this->getJson("/api/spaces/{$parent->uuid}/nodes")
            ->assertOk()
            ->assertJsonPath('parent.type', 'space')
            ->assertJsonPath('parent.id', $parent->uuid)
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.type', 'space')
            ->assertJsonPath('data.0.id', $child->uuid);
    }

    public function test_it_creates_thread_and_post_nodes_with_caller_defined_attributes(): void
    {
        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);

        Sanctum::actingAs($user);

        $threadResponse = $this->postJson('/api/form/nodes', [
            'type' => 'thread',
            'parent' => [
                'type' => 'space',
                'id' => $space->uuid,
            ],
            'attributes' => [
                'title' => 'External review',
                'purpose' => 'document_review',
                'phase' => 'awaiting_input',
            ],
        ]);

        $threadResponse
            ->assertCreated()
            ->assertJsonPath('data.type', 'thread')
            ->assertJsonPath('data.attributes.purpose', 'document_review')
            ->assertJsonPath('data.attributes.phase', 'awaiting_input');

        $thread = Thread::query()
            ->where('uuid', $threadResponse->json('data.id'))
            ->firstOrFail();

        $postResponse = $this->postJson('/api/form/nodes', [
            'type' => 'post',
            'parent' => [
                'type' => 'thread',
                'id' => $thread->uuid,
            ],
            'attributes' => [
                'post_type' => 'review.requested',
                'text' => 'Review the attached document.',
                'payload' => [
                    'external_id' => 'doc-42',
                ],
                'meta' => [
                    'source' => 'document-service',
                ],
            ],
        ]);

        $postResponse
            ->assertCreated()
            ->assertJsonPath('data.type', 'post')
            ->assertJsonPath('data.attributes.post_type', 'review.requested')
            ->assertJsonPath('data.attributes.text', 'Review the attached document.')
            ->assertJsonPath('data.attributes.payload.external_id', 'doc-42');

        $post = Post::query()
            ->where('ulid', $postResponse->json('data.id'))
            ->firstOrFail();

        $this->getJson("/api/spaces/{$space->uuid}/nodes")
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'thread',
                'id' => $thread->uuid,
            ]);

        $this->getJson("/api/threads/{$thread->uuid}/nodes")
            ->assertOk()
            ->assertJsonPath('parent.type', 'thread')
            ->assertJsonPath('data.0.type', 'post')
            ->assertJsonPath('data.0.id', $post->ulid);

        $this->getJson("/api/form/nodes/post/{$post->ulid}")
            ->assertOk()
            ->assertJsonPath('data.attributes.meta.source', 'document-service');
    }

    public function test_it_rejects_invalid_node_containment(): void
    {
        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);

        Sanctum::actingAs($user);

        $threadResponse = $this->postJson('/api/form/nodes', [
            'type' => 'thread',
            'parent' => [
                'type' => 'space',
                'id' => $space->uuid,
            ],
            'attributes' => [
                'title' => 'Valid parent thread',
            ],
        ])->assertCreated();

        $threadId = $threadResponse->json('data.id');

        $this->postJson('/api/form/nodes', [
            'type' => 'space',
            'parent' => [
                'type' => 'thread',
                'id' => $threadId,
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent.type');

        $postResponse = $this->postJson('/api/form/nodes', [
            'type' => 'post',
            'parent' => [
                'type' => 'thread',
                'id' => $threadId,
            ],
            'attributes' => [
                'text' => 'Valid parent post',
            ],
        ])->assertCreated();

        $this->postJson('/api/form/nodes', [
            'type' => 'thread',
            'parent' => [
                'type' => 'post',
                'id' => $postResponse->json('data.id'),
            ],
            'attributes' => [
                'title' => 'Invalid child thread',
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent.type');
    }

    public function test_threads_and_posts_can_contain_nodes_of_their_allowed_types(): void
    {
        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);

        Sanctum::actingAs($user);

        $parentThreadResponse = $this->postJson('/api/form/nodes', [
            'type' => 'thread',
            'parent' => [
                'type' => 'space',
                'id' => $space->uuid,
            ],
            'attributes' => [
                'title' => 'Parent thread',
            ],
        ])->assertCreated();

        $childThreadResponse = $this->postJson('/api/form/nodes', [
            'type' => 'thread',
            'parent' => [
                'type' => 'thread',
                'id' => $parentThreadResponse->json('data.id'),
            ],
            'attributes' => [
                'title' => 'Child thread',
            ],
        ])->assertCreated();

        $parentPostResponse = $this->postJson('/api/form/nodes', [
            'type' => 'post',
            'parent' => [
                'type' => 'thread',
                'id' => $childThreadResponse->json('data.id'),
            ],
            'attributes' => [
                'text' => 'Parent post',
            ],
        ])->assertCreated();

        $childPostResponse = $this->postJson('/api/form/nodes', [
            'type' => 'post',
            'parent' => [
                'type' => 'post',
                'id' => $parentPostResponse->json('data.id'),
            ],
            'attributes' => [
                'text' => 'Child post',
            ],
        ])->assertCreated();

        $this->getJson("/api/threads/{$parentThreadResponse->json('data.id')}/nodes")
            ->assertOk()
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.type', 'thread')
            ->assertJsonPath('data.0.id', $childThreadResponse->json('data.id'));

        $this->getJson("/api/threads/{$childThreadResponse->json('data.id')}/nodes")
            ->assertOk()
            ->assertJsonPath('data.0.type', 'post')
            ->assertJsonPath('data.0.id', $parentPostResponse->json('data.id'));

        $this->getJson("/api/posts/{$parentPostResponse->json('data.id')}/nodes")
            ->assertOk()
            ->assertJsonPath('parent.type', 'post')
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.type', 'post')
            ->assertJsonPath('data.0.id', $childPostResponse->json('data.id'));
    }

    public function test_it_forbids_listing_nodes_from_an_inaccessible_space(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $space = $this->accessibleSpace($owner);

        Sanctum::actingAs($intruder);

        $this->getJson("/api/spaces/{$space->uuid}/nodes")
            ->assertForbidden();
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
