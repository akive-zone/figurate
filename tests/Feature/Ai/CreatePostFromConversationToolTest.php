<?php

namespace Tests\Feature\Ai;

use App\Ai\Tools\CreatePostFromConversationTool;
use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Post;
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
        [$channel, $thread, $actor] = $this->conversationContext();

        $tool = new CreatePostFromConversationTool($thread, $channel, $actor);

        $response = json_decode($tool->handle(new ToolRequest([
            'intent' => 'subject',
            'title' => 'Need a carpenter',
            'description' => 'Need help fixing a door frame.',
            'flow_type' => 'ubid',
        ])), true, flags: JSON_THROW_ON_ERROR);

        $thread->refresh();

        $this->assertTrue($response['ok']);
        $this->assertTrue($response['created']);
        $this->assertSame('subject', $response['intent']);
        $this->assertSame('request.created', $response['post_type']);
        $this->assertSame('request_open', $thread->phase);
        $this->assertSame((new Post)->getMorphClass(), $thread->threadable_type);
        $this->assertSame(1, Request::query()->count());
        $this->assertSame('request_created', $thread->messages()->latest('id')->value('tag'));
    }

    public function test_it_creates_an_execution_post_through_the_domain_listener(): void
    {
        [$channel, $thread, $actor] = $this->conversationContext();

        $tool = new CreatePostFromConversationTool($thread, $channel, $actor);

        $tool->handle(new ToolRequest([
            'intent' => 'subject',
            'title' => 'Need a carpenter',
            'description' => 'Need help fixing a door frame.',
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
     * @return array{0: Channel, 1: Thread, 2: User}
     */
    protected function conversationContext(): array
    {
        $channel = Channel::factory()->create();
        $actor = User::factory()->create();
        $thread = Thread::factory()->create([
            'threadable_type' => $channel->getMorphClass(),
            'threadable_id' => $channel->getKey(),
            'phase' => 'conversation_open',
            'status' => 'open',
        ]);

        ChannelActorState::query()->create([
            'channel_id' => $channel->id,
            'thread_id' => $thread->id,
            'actorable_type' => $actor->getMorphClass(),
            'actorable_id' => $actor->getKey(),
            'status' => ChannelActorState::StatusActive,
        ]);

        return [$channel, $thread, $actor];
    }
}
