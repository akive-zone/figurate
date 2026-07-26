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

class PostReadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reads_an_accessible_post_by_ulid(): void
    {
        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);
        $post = $space->posts()->create([
            'type' => 'crm.conversation',
            'status' => Post::StatusActive,
            'payload' => [
                'source' => 'crm',
                'text' => 'Conversation snapshot',
            ],
            'meta' => [
                'review_requested' => true,
            ],
            'occurred_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/posts/{$post->ulid}")
            ->assertOk()
            ->assertJsonPath('data.id', $post->ulid)
            ->assertJsonPath('data.type', 'crm.conversation')
            ->assertJsonPath('data.payload.source', 'crm')
            ->assertJsonPath('data.postable.type', 'space')
            ->assertJsonPath('data.postable.id', $space->uuid);
    }

    public function test_it_reads_projected_turns_for_a_thread_post(): void
    {
        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);
        $thread = $this->threadForSpace($space);
        $post = $thread->posts()->create([
            'type' => Post::TypeMessage,
            'status' => Post::StatusActive,
            'payload' => [
                'text' => 'Review this conversation.',
            ],
            'meta' => [
                'source' => 'agent_prompt',
            ],
            'occurred_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/posts/{$post->ulid}/turns")
            ->assertOk()
            ->assertJsonPath('thread', $thread->uuid)
            ->assertJsonPath('post_id', $post->ulid)
            ->assertJsonPath('data.0.prompt_post_id', $post->id);
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
