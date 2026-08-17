<?php

namespace Tests\Feature;

use App\Ai\Support\AgentExecutor;
use App\Models\Server\Post;
use App\Models\Server\SanctumUser;
use App\Models\Server\Space;
use App\Models\Server\SpaceActorState;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\ThreadEvent;
use App\Models\Server\User;
use App\Support\Orchestrate\TaskRecord;
use App\TokenAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class PostInvocationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_invokes_a_space_post_and_returns_a_generic_task(): void
    {
        $queuedPrompt = null;
        $queuedThread = null;

        $this->mock(AgentExecutor::class, function (MockInterface $mock) use (&$queuedPrompt, &$queuedThread): void {
            $mock->shouldReceive('queue')
                ->once()
                ->withArgs(function (
                    Thread $thread,
                    Post $post,
                    User $actor,
                    ThreadActor $presenter,
                    string $broadcastSpaceId,
                ) use (&$queuedPrompt, &$queuedThread): bool {
                    $queuedPrompt = $post;
                    $queuedThread = $thread;

                    return $actor->exists
                        && $presenter->role === ThreadActor::RolePresenter
                        && $presenter->actorName() === ThreadActor::ActorCoordinator
                        && str_contains((string) $post->text, 'Identify severity')
                        && str_contains((string) $post->text, 'Source post JSON:')
                        && $broadcastSpaceId === "threads.{$thread->uuid}";
                });
        });

        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);
        $sourcePost = $this->spacePost($space, $user, [
            'source' => [
                'system' => 'crm',
                'conversation_id' => 'CRM-8842',
            ],
            'conversation' => [
                'messages' => [
                    ['sender' => 'customer', 'body' => 'The invoice download is broken.'],
                ],
            ],
        ]);
        $token = $this->apiToken($user, [
            TokenAbility::FormsSubmit->value,
            TokenAbility::InvocationsRead->value,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'crm-review-CRM-8842-v1',
        ])->postJson("/api/posts/{$sourcePost->ulid}/invocations", [
            'instructions' => 'Identify severity, missing information, and recommended action.',
        ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('data.kind', 'task')
            ->assertJsonPath('data.state', 'submitted')
            ->assertJsonPath('data.source_post.id', $sourcePost->ulid)
            ->assertJsonPath('data.source_post.type', 'crm.conversation')
            ->assertJsonPath('data.space_id', $space->uuid)
            ->assertJsonPath('data.prompt.text', 'Identify severity, missing information, and recommended action.')
            ->assertJsonPath('data.invocations', [])
            ->assertJsonPath('data.artifacts', []);

        $this->assertInstanceOf(Thread::class, $queuedThread);
        $this->assertSame($space->getMorphClass(), $queuedThread->threadable_type);
        $this->assertSame($space->id, $queuedThread->threadable_id);
        $this->assertSame('post_review', $queuedThread->purpose);

        $this->assertInstanceOf(Post::class, $queuedPrompt);
        $this->assertSame($queuedPrompt->ulid, $response->json('data.prompt.id'));
        $this->assertTrue($this->hasDerivedFromRelation($queuedPrompt, $sourcePost));

        $task = TaskRecord::fromEvent(
            ThreadEvent::query()->where('event_key', 'agent_task')->latest('id')->firstOrFail()
        );

        $this->assertInstanceOf(TaskRecord::class, $task);
        $this->assertSame($response->json('data.id'), $task->uuid);
        $this->assertSame('post_invocation', $task->protocol);
        $this->assertSame(ThreadEvent::KindOrchestration, $task->event->kind);
        $this->assertSame($user->id, $task->userId);

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/tasks/{$task->uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $task->uuid)
            ->assertJsonPath('data.source_post.id', $sourcePost->ulid)
            ->assertJsonPath('data.thread_id', $queuedThread->uuid);
    }

    public function test_it_invokes_a_thread_post_in_the_existing_thread(): void
    {
        $this->mock(AgentExecutor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('queue')->once();
        });

        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);
        $thread = $this->threadForSpace($space);
        $sourcePost = $this->threadPost($thread, $user, [
            'artifact' => [
                'system' => 'cms',
                'id' => 'REV-551',
            ],
        ]);
        $threadCount = Thread::query()->count();
        $token = $this->apiToken($user, [TokenAbility::FormsSubmit->value]);

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'artifact-review-REV-551-v1',
        ])->postJson("/api/posts/{$sourcePost->ulid}/invocations", [
            'instructions' => 'Review this artifact for publication risk.',
        ])
            ->assertAccepted()
            ->assertJsonPath('data.source_post.id', $sourcePost->ulid)
            ->assertJsonPath('data.space_id', $space->uuid)
            ->assertJsonPath('data.thread_id', $thread->uuid);

        $this->assertSame($threadCount, Thread::query()->count());
    }

    public function test_it_returns_completed_artifacts_with_source_relations(): void
    {
        $this->mock(AgentExecutor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('queue')->once();
        });

        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);
        $sourcePost = $this->spacePost($space, $user, [
            'request' => [
                'external_id' => 'repair-100',
                'urgency' => 'high',
            ],
        ], 'service.request');
        $token = $this->apiToken($user, [
            TokenAbility::FormsSubmit->value,
            TokenAbility::InvocationsRead->value,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'repair-review-100-v1',
        ])->postJson("/api/posts/{$sourcePost->ulid}/invocations", [
            'instructions' => 'Identify missing repair request information and routing.',
        ])->assertAccepted();

        $taskId = (string) $response->json('data.id');
        $promptPost = Post::query()
            ->where('ulid', (string) $response->json('data.prompt.id'))
            ->firstOrFail();
        $thread = Thread::query()
            ->where('uuid', (string) $response->json('data.thread_id'))
            ->firstOrFail();
        $invocationId = (string) fake()->uuid();
        $meta = is_array($promptPost->meta) ? $promptPost->meta : [];
        $meta['invocations'] = [
            ThreadActor::ActorCoordinator => [
                'status' => 'completed',
                'invocation_id' => $invocationId,
                'conversation_id' => (string) fake()->uuid(),
                'recorded_at' => now()->toIso8601String(),
            ],
        ];
        $promptPost->forceFill(['meta' => $meta])->save();

        $artifact = $thread->posts()->create([
            'type' => Post::TypeMessage,
            'status' => Post::StatusActive,
            'text' => 'Missing access window and evidence. Route to urgent repair triage.',
            'meta' => [
                'source' => 'agent_response',
                'in_reply_to_post_id' => $promptPost->id,
                'actor_key' => ThreadActor::ActorCoordinator,
                'invocation_id' => $invocationId,
            ],
            'occurred_at' => now(),
        ]);
        $artifact->attachRelation($sourcePost, Post::RelationRoleDerivedFrom);

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/tasks/{$taskId}")
            ->assertOk()
            ->assertJsonPath('data.state', 'completed')
            ->assertJsonPath('data.artifacts.0.id', $artifact->ulid)
            ->assertJsonPath('data.artifacts.0.text', 'Missing access window and evidence. Route to urgent repair triage.')
            ->assertJsonPath('data.artifacts.0.source_relations.0.role', Post::RelationRoleDerivedFrom)
            ->assertJsonPath('data.artifacts.0.source_relations.0.target.type', 'post')
            ->assertJsonPath('data.artifacts.0.source_relations.0.target.id', $sourcePost->ulid);
    }

    public function test_it_enforces_the_forms_submit_and_invocations_read_ability_split(): void
    {
        $this->mock(AgentExecutor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('queue')->once();
        });

        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);
        $sourcePost = $this->spacePost($space, $user);
        $readOnlyToken = $this->apiToken($user, [TokenAbility::InvocationsRead->value]);
        $submitOnlyToken = $this->apiToken($user, [TokenAbility::FormsSubmit->value]);

        $this->withHeaders([
            'Authorization' => "Bearer {$readOnlyToken}",
            'Idempotency-Key' => 'ability-check-denied',
        ])->postJson("/api/posts/{$sourcePost->ulid}/invocations", [
            'instructions' => 'Review the source.',
        ])
            ->assertForbidden()
            ->assertJsonPath('required_ability', TokenAbility::FormsSubmit->value);

        $this->resetRequestAuth();

        $created = $this->withHeaders([
            'Authorization' => "Bearer {$submitOnlyToken}",
            'Idempotency-Key' => 'ability-check-created',
        ])->postJson("/api/posts/{$sourcePost->ulid}/invocations", [
            'instructions' => 'Review the source.',
        ])->assertAccepted();

        $this->resetRequestAuth();

        $this->withHeaders([
            'Authorization' => "Bearer {$submitOnlyToken}",
        ])->getJson('/api/tasks/'.$created->json('data.id'))
            ->assertForbidden()
            ->assertJsonPath('required_ability', TokenAbility::InvocationsRead->value);
    }

    public function test_invocation_creation_is_idempotent(): void
    {
        $this->mock(AgentExecutor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('queue')->once();
        });

        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);
        $sourcePost = $this->spacePost($space, $user);
        $token = $this->apiToken($user, [TokenAbility::FormsSubmit->value]);
        $headers = [
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'same-source-review-v1',
        ];
        $payload = ['instructions' => 'Review this source once.'];

        $first = $this->withHeaders($headers)
            ->postJson("/api/posts/{$sourcePost->ulid}/invocations", $payload)
            ->assertAccepted();

        $second = $this->withHeaders($headers)
            ->postJson("/api/posts/{$sourcePost->ulid}/invocations", $payload);

        $second
            ->assertAccepted()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertExactJson($first->json());

        $this->assertSame(1, ThreadEvent::query()->where('event_key', 'agent_task')->count());

        $this->withHeaders($headers)
            ->postJson("/api/posts/{$sourcePost->ulid}/invocations", [
                'instructions' => 'Review this source differently.',
            ])->assertConflict();
    }

    public function test_it_rejects_oversized_source_envelopes(): void
    {
        $this->mock(AgentExecutor::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('queue');
        });

        $user = User::factory()->create();
        $space = $this->accessibleSpace($user);
        $sourcePost = $this->spacePost($space, $user, [
            'blob' => str_repeat('x', 70000),
        ]);
        $token = $this->apiToken($user, [TokenAbility::FormsSubmit->value]);

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Idempotency-Key' => 'oversized-source-v1',
        ])->postJson("/api/posts/{$sourcePost->ulid}/invocations", [
            'instructions' => 'Review this oversized source.',
        ])->assertUnprocessable();

        $this->assertSame(0, ThreadEvent::query()->where('event_key', 'agent_task')->count());
    }

    public function test_it_does_not_expose_another_users_task(): void
    {
        $this->mock(AgentExecutor::class, function (MockInterface $mock): void {
            $mock->shouldReceive('queue')->once();
        });

        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $space = $this->accessibleSpace($owner);
        $sourcePost = $this->spacePost($space, $owner);
        $ownerToken = $this->apiToken($owner, [TokenAbility::FormsSubmit->value]);
        $intruderToken = $this->apiToken($intruder, [TokenAbility::InvocationsRead->value]);

        $created = $this->withHeaders([
            'Authorization' => "Bearer {$ownerToken}",
            'Idempotency-Key' => 'owner-task-v1',
        ])->postJson("/api/posts/{$sourcePost->ulid}/invocations", [
            'instructions' => 'Review this source.',
        ])->assertAccepted();

        $this->resetRequestAuth();

        $this->withHeaders([
            'Authorization' => "Bearer {$intruderToken}",
        ])->getJson('/api/tasks/'.$created->json('data.id'))
            ->assertNotFound();
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

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function spacePost(Space $space, User $sender, array $payload = [], string $type = 'crm.conversation'): Post
    {
        $post = $space->posts()->create([
            'type' => $type,
            'status' => Post::StatusActive,
            'payload' => $payload === [] ? ['text' => 'Review this source.'] : $payload,
            'occurred_at' => now(),
        ]);
        $post->attachRelation($sender, Post::RelationRoleSender);

        return $post;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function threadPost(Thread $thread, User $sender, array $payload = [], string $type = 'external.artifact'): Post
    {
        $post = $thread->posts()->create([
            'type' => $type,
            'status' => Post::StatusActive,
            'payload' => $payload === [] ? ['text' => 'Review this artifact.'] : $payload,
            'occurred_at' => now(),
        ]);
        $post->attachRelation($sender, Post::RelationRoleSender);

        return $post;
    }

    protected function threadForSpace(Space $space): Thread
    {
        return $space->threads()->create([
            'purpose' => Thread::PurposeMain,
            'title' => 'External Review',
            'phase' => Thread::PhaseInitial,
            'status' => 'open',
        ]);
    }

    /**
     * @param  list<string>  $abilities
     */
    protected function apiToken(User $user, array $abilities): string
    {
        return SanctumUser::query()
            ->findOrFail($user->id)
            ->createToken('api:test-token', $abilities)
            ->plainTextToken;
    }

    protected function hasDerivedFromRelation(Post $source, Post $target): bool
    {
        return $source->relations()
            ->where('role', Post::RelationRoleDerivedFrom)
            ->where('relationable_type', $target->getMorphClass())
            ->where('relationable_id', $target->getKey())
            ->exists();
    }

    protected function resetRequestAuth(): void
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
    }
}
