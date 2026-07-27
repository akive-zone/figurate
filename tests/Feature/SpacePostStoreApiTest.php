<?php

namespace Tests\Feature;

use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpacePostStoreApiTest extends TestCase
{
    use RefreshDatabase;

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
                                'sender' => 'agent',
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
