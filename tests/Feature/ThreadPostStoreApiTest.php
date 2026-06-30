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

    public function test_it_stores_arbitrary_context_payloads_in_a_thread_post(): void
    {
        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);
        $thread = $this->threadForSpace($space);

        Sanctum::actingAs($user);

        $this->postJson("/threads/{$thread->uuid}/posts", [
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
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', Post::TypeMessage)
            ->assertJsonPath('data.text', 'Review this fulfilment exchange.')
            ->assertJsonPath('data.payload.source.conversation_id', 'crm-conv-2002')
            ->assertJsonPath('data.thread.id', $thread->uuid)
            ->assertJsonPath('data.thread.space_id', $space->uuid);

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

    public function test_it_forbids_posting_to_an_inaccessible_thread(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $space = $this->accessibleSpace($owner);
        $thread = $this->threadForSpace($space);

        Sanctum::actingAs($intruder);

        $this->postJson("/threads/{$thread->uuid}/posts", [
            'text' => 'not allowed',
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
