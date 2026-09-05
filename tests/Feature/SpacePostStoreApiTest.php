<?php

namespace Tests\Feature;

use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpacePostStoreApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_arbitrary_context_payloads_through_the_space_posts_endpoint(): void
    {
        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/spaces/{$space->uuid}/posts", [
            'type' => 'crm.conversation',
            'text' => 'Customer asked for refund after failed fulfilment.',
            'source' => [
                'system' => 'crm',
                'conversation_id' => 'crm-conv-1001',
            ],
            'conversation' => [
                'messages' => [
                    [
                        'sender' => 'customer',
                        'body' => 'The order never arrived.',
                    ],
                ],
            ],
            'meta' => [
                'review_requested' => true,
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', 'crm.conversation')
            ->assertJsonPath('data.status', Post::StatusActive)
            ->assertJsonPath('data.text', 'Customer asked for refund after failed fulfilment.')
            ->assertJsonPath('data.payload.source.conversation_id', 'crm-conv-1001')
            ->assertJsonPath('data.payload.conversation.messages.0.body', 'The order never arrived.')
            ->assertJsonPath('data.payload.text', 'Customer asked for refund after failed fulfilment.')
            ->assertJsonPath('data.meta.review_requested', true)
            ->assertJsonPath('data.postable.type', 'space')
            ->assertJsonPath('data.postable.id', $space->uuid);

        $post = Post::query()
            ->where('ulid', $response->json('data.id'))
            ->firstOrFail();

        $this->assertSame($space->getMorphClass(), $post->postable_type);
        $this->assertSame($space->id, $post->postable_id);
        $this->assertSame('crm.conversation', $post->type);
        $this->assertSame('crm-conv-1001', data_get($post->payload, 'source.conversation_id'));
        $this->assertSame('Customer asked for refund after failed fulfilment.', $post->text);
    }

    public function test_it_lists_direct_space_posts(): void
    {
        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'CRM Review',
            'phase' => 'context_review',
            'status' => 'open',
        ]);
        $firstPost = $space->posts()->create([
            'type' => 'crm.conversation',
            'status' => Post::StatusActive,
            'payload' => ['external_id' => 'space-post-1'],
            'occurred_at' => now(),
        ]);
        $secondPost = $space->posts()->create([
            'type' => 'crm.review',
            'status' => Post::StatusActive,
            'payload' => ['external_id' => 'space-post-2'],
            'occurred_at' => now(),
        ]);
        $thread->posts()->create([
            'type' => 'crm.thread-only',
            'status' => Post::StatusActive,
            'payload' => ['external_id' => 'thread-post-1'],
            'occurred_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/spaces/{$space->uuid}/posts")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'id' => $firstPost->ulid,
                'type' => 'crm.conversation',
            ])
            ->assertJsonFragment([
                'id' => $secondPost->ulid,
                'type' => 'crm.review',
            ])
            ->assertJsonPath('meta.per_page', 25);
    }

    public function test_it_stores_arbitrary_context_payloads_in_a_space_post(): void
    {
        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);

        Sanctum::actingAs($user);

        $this->postJson('/api/nodes', [
            'type' => 'post',
            'parent' => [
                'type' => 'space',
                'id' => $space->uuid,
            ],
            'attributes' => [
                'post_type' => 'crm.conversation',
                'text' => 'Customer asked for refund after failed fulfilment.',
                'payload' => [
                    'source' => [
                        'system' => 'crm',
                        'conversation_id' => 'crm-conv-1001',
                    ],
                    'conversation' => [
                        'customer' => [
                            'id' => 'cust_123',
                            'name' => 'Ada Lovelace',
                        ],
                        'messages' => [
                            [
                                'sender' => 'customer',
                                'body' => 'The order never arrived.',
                            ],
                            [
                                'sender' => 'support',
                                'body' => 'I can check the fulfilment status.',
                            ],
                        ],
                    ],
                ],
                'meta' => [
                    'review_requested' => true,
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'post')
            ->assertJsonPath('data.attributes.post_type', 'crm.conversation')
            ->assertJsonPath('data.attributes.text', 'Customer asked for refund after failed fulfilment.')
            ->assertJsonPath('data.attributes.payload.source.conversation_id', 'crm-conv-1001')
            ->assertJsonPath('data.attributes.payload.conversation.messages.0.body', 'The order never arrived.')
            ->assertJsonPath('data.attributes.meta.review_requested', true);

        $post = Post::query()->latest('id')->firstOrFail();

        $this->assertSame($space->getMorphClass(), $post->postable_type);
        $this->assertSame($space->id, $post->postable_id);
        $this->assertSame('crm.conversation', $post->type);
        $this->assertSame('crm-conv-1001', data_get($post->payload, 'source.conversation_id'));
        $this->assertSame('Customer asked for refund after failed fulfilment.', $post->text);

        $this->assertDatabaseHas('post_relations', [
            'post_id' => $post->id,
            'relationable_type' => $user->getMorphClass(),
            'relationable_id' => $user->id,
            'role' => Post::RelationRoleSender,
        ]);
    }

    public function test_it_forbids_posting_to_an_inaccessible_space(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $space = $this->accessibleSpace($owner);

        Sanctum::actingAs($intruder);

        $this->postJson('/api/nodes', [
            'type' => 'post',
            'parent' => [
                'type' => 'space',
                'id' => $space->uuid,
            ],
        ])->assertForbidden();
    }

    public function test_it_forbids_posting_directly_to_an_inaccessible_space(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $space = $this->accessibleSpace($owner);

        Sanctum::actingAs($intruder);

        $this->postJson("/api/spaces/{$space->uuid}/posts", [
            'type' => 'crm.conversation',
            'text' => 'not allowed',
        ])->assertForbidden();
    }

    public function test_it_stores_plain_values_inside_a_node_payload(): void
    {
        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);

        Sanctum::actingAs($user);

        $this->postJson('/api/nodes', [
            'type' => 'post',
            'parent' => [
                'type' => 'space',
                'id' => $space->uuid,
            ],
            'attributes' => [
                'payload' => [
                    'value' => 'plain CRM transcript export',
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.attributes.payload.value', 'plain CRM transcript export');
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
