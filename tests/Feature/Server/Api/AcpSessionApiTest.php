<?php

namespace Tests\Feature\Server\Api;

use App\Ai\Support\ChatAgentExecutor;
use App\Models\Server\AgentTask;
use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use Database\Factories\ChannelFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AcpSessionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_lists_and_loads_acp_sessions(): void
    {
        $user = $this->makeUser();
        $channel = $this->accessibleChannel($user);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/acp/sessions', [
            'channel_uuid' => $channel->uuid,
            'title' => 'ACP Build Session',
            'purpose' => Thread::PurposeExecution,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'ACP Build Session')
            ->assertJsonPath('data.channel.id', $channel->uuid);

        $sessionId = (string) $response->json('data.id');
        $thread = Thread::query()->where('uuid', $sessionId)->firstOrFail();

        $this->assertDatabaseHas('thread_actors', [
            'thread_id' => $thread->id,
            'actorable_type' => ThreadActor::ActorOrderAgent,
            'role' => ThreadActor::RolePresenter,
            'status' => ThreadActor::StatusActive,
        ]);

        Message::query()->create([
            'messageable_type' => $thread->getMorphClass(),
            'messageable_id' => $thread->id,
            'senderable_type' => $user->getMorphClass(),
            'senderable_id' => $user->id,
            'type' => 'text',
            'text' => 'Please inspect the workspace.',
            'meta' => ['source' => 'acp_prompt'],
        ]);

        Message::query()->create([
            'messageable_type' => $thread->getMorphClass(),
            'messageable_id' => $thread->id,
            'senderable_type' => null,
            'senderable_id' => null,
            'type' => 'text',
            'text' => 'Inspection complete.',
            'meta' => [
                'source' => 'agent_response',
                'actor_key' => ThreadActor::ActorOrderAgent,
            ],
        ]);

        $this->getJson('/api/acp/sessions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $sessionId);

        $this->getJson("/api/acp/sessions/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('data.id', $sessionId)
            ->assertJsonPath('data.messages.1.role', 'assistant')
            ->assertJsonPath('data.messages.1.text', 'Inspection complete.');
    }

    public function test_it_prompts_and_cancels_acp_tasks(): void
    {
        $this->mock(ChatAgentExecutor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('queue')->once();
        });

        $user = $this->makeUser();
        $channel = $this->accessibleChannel($user);

        $this->actingAs($user, 'sanctum');

        $sessionResponse = $this->postJson('/api/acp/sessions', [
            'channel_uuid' => $channel->uuid,
            'title' => 'Cancelable ACP Session',
            'purpose' => Thread::PurposeExecution,
        ])->assertCreated();

        $sessionId = (string) $sessionResponse->json('data.id');

        $promptResponse = $this->postJson("/api/acp/sessions/{$sessionId}/prompt", [
            'text' => 'Run the task.',
        ]);

        $promptResponse->assertAccepted()
            ->assertJsonPath('data.session_id', $sessionId)
            ->assertJsonPath('data.state', 'submitted');

        $taskId = (string) $promptResponse->json('data.id');
        $task = AgentTask::query()->where('uuid', $taskId)->firstOrFail();

        $this->assertSame('submitted', $task->status);
        $this->assertNotNull($task->message_id);
        $this->assertSame($user->id, $task->user_id);

        $this->getJson("/api/acp/tasks/{$taskId}")
            ->assertOk()
            ->assertJsonPath('data.id', $taskId)
            ->assertJsonPath('data.state', 'submitted');

        $this->postJson("/api/acp/tasks/{$taskId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.id', $taskId)
            ->assertJsonPath('data.state', 'canceled');

        $task->refresh();
        $this->assertSame('canceled', $task->status);
        $this->assertNotNull($task->canceled_at);

        $this->getJson("/api/acp/tasks/{$taskId}")
            ->assertOk()
            ->assertJsonPath('data.state', 'canceled');
    }

    protected function accessibleChannel(User $user): Channel
    {
        $channel = ChannelFactory::new()->create();

        ChannelActorState::query()->create([
            'channel_id' => $channel->id,
            'thread_id' => null,
            'actorable_type' => $user->getMorphClass(),
            'actorable_id' => $user->id,
            'status' => ChannelActorState::StatusActive,
        ]);

        return $channel;
    }

    protected function makeUser(): User
    {
        return User::query()->create([
            'name' => 'ACP Tester',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'type' => 'person',
            'provider' => null,
            'provider_id' => null,
            'status' => 'active',
        ]);
    }
}
