<?php

namespace Tests\Feature\A2a;

use App\Ai\Tools\DelegateA2aTaskTool;
use App\Ai\Tools\InvokeA2aAgentTool;
use App\Ai\Tools\ListAvailableA2aAgentsTool;
use App\Models\Server\Channel;
use App\Models\Server\Thread;
use App\Models\Server\ThreadEvent;
use App\Models\Server\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request as ToolRequest;
use Tests\TestCase;

class OutboundA2aToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_filters_out_untrusted_a2a_agents_from_the_available_list(): void
    {
        config()->set('a2a.outbound.enabled', true);
        config()->set('a2a.outbound.trust', [
            'allow_http' => false,
            'allow_private_network' => false,
        ]);
        config()->set('a2a.outbound.agents', [
            'trusted' => [
                'label' => 'Trusted Agent',
                'endpoint' => 'https://agents.example/rpc',
                'allowed_methods' => ['message/send'],
            ],
            'blocked' => [
                'label' => 'Blocked Agent',
                'endpoint' => 'http://127.0.0.1:4318/rpc',
                'allowed_methods' => ['message/send'],
            ],
        ]);

        $tool = new ListAvailableA2aAgentsTool;
        $response = json_decode($tool->handle(new ToolRequest([])), true, flags: JSON_THROW_ON_ERROR);

        $this->assertTrue($response['ok']);
        $this->assertTrue($response['enabled']);
        $this->assertSame(1, $response['count']);
        $this->assertSame(1, $response['filtered_out_count']);
        $this->assertSame('trusted', data_get($response, 'agents.0.id'));
    }

    public function test_it_denies_invocation_for_an_untrusted_a2a_agent_endpoint(): void
    {
        config()->set('a2a.outbound.enabled', true);
        config()->set('a2a.outbound.trust', [
            'allow_http' => false,
            'allow_private_network' => false,
        ]);
        config()->set('a2a.outbound.agents', [
            'blocked' => [
                'label' => 'Blocked Agent',
                'endpoint' => 'http://127.0.0.1:4318/rpc',
                'allowed_methods' => ['message/send'],
            ],
        ]);

        Http::preventStrayRequests();

        [$thread, $actor] = $this->conversationContext();
        $tool = new InvokeA2aAgentTool($thread, $actor);
        $response = json_decode($tool->handle(new ToolRequest([
            'agent' => 'blocked',
            'method' => 'message/send',
            'params' => [
                'message' => [
                    'role' => 'user',
                    'parts' => [['text' => 'Hello']],
                ],
            ],
        ])), true, flags: JSON_THROW_ON_ERROR);

        $this->assertTrue($response['ok']);
        $this->assertFalse($response['allowed']);
        $this->assertSame('Plain HTTP URLs are not allowed by policy.', $response['error']);

        Http::assertNothingSent();

        $event = ThreadEvent::query()->latest('id')->firstOrFail();
        $this->assertSame('a2a.outbound.failure', $event->event_type);
        $this->assertSame('a2a_endpoint_denied', data_get($event->payload, 'error_code'));
    }

    public function test_it_denies_delegation_for_an_untrusted_a2a_agent_endpoint(): void
    {
        config()->set('a2a.outbound.enabled', true);
        config()->set('a2a.outbound.trust', [
            'allow_http' => false,
            'allow_private_network' => false,
        ]);
        config()->set('a2a.outbound.agents', [
            'blocked' => [
                'label' => 'Blocked Agent',
                'endpoint' => 'http://127.0.0.1:4318/rpc',
                'allowed_methods' => ['message/send', 'tasks/get'],
            ],
        ]);

        Http::preventStrayRequests();

        [$thread, $actor] = $this->conversationContext();
        $tool = new DelegateA2aTaskTool($thread, $actor);
        $response = json_decode($tool->handle(new ToolRequest([
            'agent' => 'blocked',
            'message' => 'Review this diff.',
        ])), true, flags: JSON_THROW_ON_ERROR);

        $this->assertFalse($response['ok']);
        $this->assertSame('config', $response['stage']);
        $this->assertSame('Plain HTTP URLs are not allowed by policy.', $response['error']);

        Http::assertNothingSent();
        $this->assertDatabaseCount('agent_tasks', 0);
    }

    /**
     * @return array{0: Thread, 1: User}
     */
    protected function conversationContext(): array
    {
        $channel = Channel::factory()->create();
        $actor = User::factory()->create();
        $thread = Thread::factory()->create([
            'threadable_type' => $channel->getMorphClass(),
            'threadable_id' => $channel->getKey(),
            'purpose' => Thread::PurposeExecution,
            'status' => 'open',
        ]);

        return [$thread, $actor];
    }
}
