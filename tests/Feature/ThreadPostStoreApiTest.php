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

class ThreadPostStoreApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_arbitrary_context_payloads_through_the_thread_posts_endpoint(): void
    {
        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);
        $thread = $this->threadForSpace($space);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/threads/{$thread->uuid}/posts", [
            'type' => 'crm.conversation',
            'text' => 'Review this fulfilment exchange.',
            'source' => [
                'system' => 'crm',
                'conversation_id' => 'crm-conv-2002',
            ],
            'conversation' => [
                'messages' => [
                    [
                        'sender' => 'customer',
                        'body' => 'I still have no tracking number.',
                    ],
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', 'crm.conversation')
            ->assertJsonPath('data.status', Post::StatusActive)
            ->assertJsonPath('data.text', 'Review this fulfilment exchange.')
            ->assertJsonPath('data.payload.source.conversation_id', 'crm-conv-2002')
            ->assertJsonPath('data.payload.conversation.messages.0.body', 'I still have no tracking number.')
            ->assertJsonPath('data.payload.text', 'Review this fulfilment exchange.')
            ->assertJsonPath('data.postable.type', 'thread')
            ->assertJsonPath('data.postable.id', $thread->uuid)
            ->assertJsonPath('data.thread.id', $thread->uuid)
            ->assertJsonPath('data.thread.space_id', $space->uuid);

        $post = Post::query()
            ->where('ulid', $response->json('data.id'))
            ->firstOrFail();

        $this->assertSame($thread->getMorphClass(), $post->postable_type);
        $this->assertSame($thread->id, $post->postable_id);
        $this->assertSame('crm.conversation', $post->type);
        $this->assertSame('crm-conv-2002', data_get($post->payload, 'source.conversation_id'));
    }

    public function test_it_lists_direct_thread_posts(): void
    {
        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);
        $thread = $this->threadForSpace($space);
        $otherThread = $this->threadForSpace($space);
        $firstPost = $thread->posts()->create([
            'type' => 'crm.conversation',
            'status' => Post::StatusActive,
            'payload' => ['external_id' => 'thread-post-1'],
            'occurred_at' => now(),
        ]);
        $secondPost = $thread->posts()->create([
            'type' => 'crm.review',
            'status' => Post::StatusActive,
            'payload' => ['external_id' => 'thread-post-2'],
            'occurred_at' => now(),
        ]);
        $otherThread->posts()->create([
            'type' => 'crm.other-thread',
            'status' => Post::StatusActive,
            'payload' => ['external_id' => 'other-thread-post-1'],
            'occurred_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/threads/{$thread->uuid}/posts")
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

    public function test_it_stores_arbitrary_context_payloads_in_a_post_node(): void
    {
        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);
        $thread = $this->threadForSpace($space);

        Sanctum::actingAs($user);

        $this->postJson('/api/nodes', [
            'type' => 'post',
            'parent' => [
                'type' => 'thread',
                'id' => $thread->uuid,
            ],
            'attributes' => [
                'text' => 'Review this fulfilment exchange.',
                'payload' => [
                    'source' => [
                        'system' => 'crm',
                        'conversation_id' => 'crm-conv-2002',
                    ],
                    'conversation' => [
                        'messages' => [
                            [
                                'sender' => 'customer',
                                'body' => 'I still have no tracking number.',
                            ],
                        ],
                    ],
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'post')
            ->assertJsonPath('data.attributes.post_type', Post::TypeMessage)
            ->assertJsonPath('data.attributes.text', 'Review this fulfilment exchange.')
            ->assertJsonPath('data.attributes.payload.source.conversation_id', 'crm-conv-2002');

        $post = Post::query()->latest('id')->firstOrFail();

        $this->assertSame($thread->getMorphClass(), $post->postable_type);
        $this->assertSame($thread->id, $post->postable_id);
        $this->assertSame('I still have no tracking number.', data_get($post->payload, 'conversation.messages.0.body'));

        $this->assertDatabaseHas('post_relations', [
            'post_id' => $post->id,
            'relationable_type' => $user->getMorphClass(),
            'relationable_id' => $user->id,
            'role' => Post::RelationRoleSender,
        ]);
    }

    public function test_it_forbids_creating_a_post_node_in_an_inaccessible_thread(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $space = $this->accessibleSpace($owner);
        $thread = $this->threadForSpace($space);

        Sanctum::actingAs($intruder);

        $this->postJson('/api/nodes', [
            'type' => 'post',
            'parent' => [
                'type' => 'thread',
                'id' => $thread->uuid,
            ],
            'attributes' => [
                'text' => 'not allowed',
            ],
        ])->assertForbidden();
    }

    public function test_it_forbids_posting_directly_to_an_inaccessible_thread(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $space = $this->accessibleSpace($owner);
        $thread = $this->threadForSpace($space);

        Sanctum::actingAs($intruder);

        $this->postJson("/api/threads/{$thread->uuid}/posts", [
            'type' => 'crm.conversation',
            'text' => 'not allowed',
        ])->assertForbidden();
    }

    public function test_it_forbids_listing_posts_for_an_inaccessible_thread(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $space = $this->accessibleSpace($owner);
        $thread = $this->threadForSpace($space);

        Sanctum::actingAs($intruder);

        $this->getJson("/api/threads/{$thread->uuid}/posts")
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

    protected function threadForSpace(Space $space): Thread
    {
        return $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'CRM Review',
            'phase' => 'context_review',
            'status' => 'open',
        ]);
    }
}
