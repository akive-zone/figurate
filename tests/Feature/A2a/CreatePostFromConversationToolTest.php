<?php

namespace Tests\Feature\A2a;

use App\Ai\Tools\CreatePostFromConversationTool;
use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Figurate\FulfillmentManager\Models\Order;
use Figurate\FulfillmentManager\Models\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request as ToolRequest;
use Tests\TestCase;

class CreatePostFromConversationToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_subject_post_through_the_domain_listener(): void
    {
        [$space, $thread, $actor] = $this->conversationContext();

        $tool = new CreatePostFromConversationTool($thread, $space, $actor);

        $response = json_decode($tool->handle(new ToolRequest([
            'intent' => 'subject',
            'subject' => [
                'title' => 'Need a carpenter',
                'description' => 'Need help fixing a door frame.',
            ],
        ])), true, flags: JSON_THROW_ON_ERROR);

        $thread->refresh();
        $request = Request::query()->sole();

        $this->assertTrue($response['ok']);
        $this->assertTrue($response['created']);
        $this->assertSame('subject', $response['intent']);
        $this->assertSame('request.created', $response['post_type']);
        $this->assertSame('request_open', $thread->phase);
        $this->assertSame((new Post)->getMorphClass(), $thread->threadable_type);
        $this->assertSame(1, Request::query()->count());
        $this->assertSame($actor->id, $request->primaryRequester()?->id);
        $this->assertTrue($request->hasUserActor($actor, Request::ActionAsker));
        $this->assertDatabaseHas('post_relations', [
            'post_id' => $request->id,
            'relationable_type' => $actor->getMorphClass(),
            'relationable_id' => $actor->id,
            'role' => Request::ActionAsker,
        ]);
        $this->assertSame('request_created', $thread->messages()->latest('id')->value('tag'));
    }

    public function test_it_creates_an_execution_post_through_the_domain_listener(): void
    {
        [$space, $thread, $actor] = $this->conversationContext();

        $tool = new CreatePostFromConversationTool($thread, $space, $actor);

        $tool->handle(new ToolRequest([
            'intent' => 'subject',
            'subject' => [
                'title' => 'Need a carpenter',
                'description' => 'Need help fixing a door frame.',
            ],
        ]));

        $response = json_decode($tool->handle(new ToolRequest([
            'intent' => 'execution',
            'title' => 'Begin repair execution',
            'description' => 'Worker confirmed start.',
        ])), true, flags: JSON_THROW_ON_ERROR);

        $thread->refresh();

        $this->assertTrue($response['ok']);
        $this->assertTrue($response['created']);
        $this->assertSame('execution', $response['intent']);
        $this->assertSame('order.created', $response['post_type']);
        $this->assertSame('order_active', $thread->phase);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame('order_post_created', $thread->messages()->latest('id')->value('tag'));
    }

    /**
     * @return array{0: Space, 1: Thread, 2: User}
     */
    protected function conversationContext(): array
    {
        $space = Space::factory()->create();
        $actor = User::factory()->create();
        $thread = Thread::factory()->create([
            'threadable_type' => $space->getMorphClass(),
            'threadable_id' => $space->getKey(),
            'phase' => 'conversation_open',
            'status' => 'open',
        ]);

        SpaceActorState::query()->create([
            'space_id' => $space->id,
            'thread_id' => $thread->id,
            'actorable_type' => $actor->getMorphClass(),
            'actorable_id' => $actor->getKey(),
            'status' => SpaceActorState::StatusActive,
        ]);

        return [$space, $thread, $actor];
    }
}
