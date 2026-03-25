<?php

namespace Tests\Feature;

use App\Features\Actions\Conversation\StoreThreadMessage;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagePostProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_projects_thread_message_into_a_message_post_and_sender_relation(): void
    {
        $sender = User::factory()->create();
        $space = Space::factory()->create();
        $thread = $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'Projection Thread',
            'phase' => 'execution',
            'status' => 'open',
        ]);

        $post = app(StoreThreadMessage::class)->execute(
            thread: $thread,
            sender: $sender,
            text: 'Projected to post',
            type: 'text',
        );

        $this->assertNotNull($post);
        $this->assertEquals(Post::TypeMessage, $post->type);
        $this->assertSame(Post::StatusActive, $post->status);
        $this->assertSame('Projected to post', $post->text);

        $this->assertDatabaseHas('post_relations', [
            'post_id' => $post->id,
            'relationable_type' => $sender->getMorphClass(),
            'relationable_id' => $sender->getKey(),
            'role' => Post::RelationRoleSender,
        ]);
    }
}
