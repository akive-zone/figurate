<?php

namespace Tests\Feature\Server\Api;

use App\Ai\Support\A2a\A2aMethodRouter;
use App\Ai\Support\ChatAgentExecutor;
use App\Models\Server\AgentTask;
use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\User;
use Database\Factories\ChannelFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class A2aTaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_a2a_tasks_in_agent_tasks(): void
    {
        $this->mock(ChatAgentExecutor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('queue')->once();
        });

        $user = $this->makeUser();
        $channel = $this->accessibleChannel($user);

        Sanctum::actingAs($user, [
            'a2a:message.send',
            'a2a:task.read',
            'a2a:task.cancel',
        ]);

        $router = app(A2aMethodRouter::class);

        $sendResponse = $router->handle('message/send', [
            'user_uuid' => $user->uuid,
            'channel' => $channel->uuid,
            'content' => [
                'text' => 'Run the A2A task.',
            ],
        ]);

        $this->assertArrayHasKey('result', $sendResponse);
        $this->assertSame('submitted', data_get($sendResponse, 'result.task.status.state'));

        $taskId = (string) data_get($sendResponse, 'result.task.id');
        $task = AgentTask::query()->latest('id')->firstOrFail();

        $this->assertSame('submitted', $task->status);
        $this->assertSame($taskId, data_get($task->last_payload, 'local.public_id'));
        $this->assertSame('a2a', data_get($task->last_payload, 'local.protocol'));
        $this->assertSame($user->getMorphClass(), data_get($task->last_payload, 'local.owner.subject_type'));
        $this->assertSame((string) $user->id, (string) data_get($task->last_payload, 'local.owner.subject_id'));

        $getResponse = $router->handle('tasks/get', [
            'taskId' => $taskId,
        ]);

        $this->assertArrayHasKey('result', $getResponse);
        $this->assertSame($taskId, data_get($getResponse, 'result.task.id'));
        $this->assertSame('submitted', data_get($getResponse, 'result.task.status.state'));

        $cancelResponse = $router->handle('tasks/cancel', [
            'taskId' => $taskId,
        ]);

        $this->assertArrayHasKey('result', $cancelResponse);
        $this->assertSame($taskId, data_get($cancelResponse, 'result.task.id'));
        $this->assertSame('canceled', data_get($cancelResponse, 'result.task.status.state'));

        $task->refresh();
        $this->assertSame('canceled', $task->status);
        $this->assertNotNull($task->canceled_at);
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
            'name' => 'A2A Tester',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'type' => 'person',
            'provider' => null,
            'provider_id' => null,
            'status' => 'active',
        ]);
    }
}
