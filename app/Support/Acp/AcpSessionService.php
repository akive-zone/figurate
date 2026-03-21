<?php

namespace App\Support\Acp;

use App\Features\Actions\Chat\EnsureThreadMembership;
use App\Features\Actions\Chat\ResolveActiveThreadPresenters;
use App\Features\Operations\Chat\DispatchPromptOperation;
use App\Models\Server\AgentTask;
use App\Models\Server\Channel;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;
use App\Support\Orchestrate\AgentTaskService;
use App\Support\Orchestrate\MessageTaskService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AcpSessionService
{
    public function __construct(
        protected DispatchPromptOperation $dispatchPromptOperation,
        protected EnsureThreadMembership $ensureThreadMembership,
        protected ResolveActiveThreadPresenters $resolveActiveThreadPresenters,
        protected AgentTaskService $agentTaskService,
        protected MessageTaskService $messageTaskService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listSessions(User $actor): array
    {
        Gate::forUser($actor)->authorize('viewAny', Thread::class);

        $channelIds = $this->visibleChannelsQuery($actor)->pluck('id');

        if ($channelIds->isEmpty()) {
            return [];
        }

        return Thread::query()
            ->where('threadable_type', (new Channel)->getMorphClass())
            ->whereIn('threadable_id', $channelIds->all())
            ->with('threadable')
            ->withMax('messages', 'created_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Thread $thread): bool => $thread->threadable instanceof Channel)
            ->map(fn (Thread $thread): array => $this->sessionPayload($thread, $thread->threadable))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function createSession(User $actor, string $channelUuid, ?string $title = null, ?string $purpose = null): array
    {
        $channel = Channel::query()
            ->where('uuid', $channelUuid)
            ->firstOrFail();

        Gate::forUser($actor)->authorize('update', $channel);

        $resolvedPurpose = $this->resolvedPurpose($purpose);
        $resolvedTitle = $this->trimmedString($title) ?? $this->defaultTitle($resolvedPurpose);

        $thread = DB::transaction(function () use ($actor, $channel, $resolvedPurpose, $resolvedTitle): Thread {
            $thread = $channel->threads()->create([
                'title' => $resolvedTitle,
                'purpose' => $resolvedPurpose,
                'phase' => $this->defaultPhase($resolvedPurpose),
                'status' => 'open',
            ]);

            $thread->actors()->create([
                'actorable_type' => $this->defaultHandlerActor($resolvedPurpose),
                'actorable_id' => null,
                'role' => ThreadActor::RolePresenter,
                'status' => ThreadActor::StatusActive,
                'priority' => 1,
                'config' => null,
            ]);

            $this->ensureThreadMembership->execute($thread, $actor);
            $this->markActiveThread($channel, $actor, $thread);

            return $thread;
        });

        return $this->sessionPayload($thread->fresh(), $channel);
    }

    /**
     * @return array<string, mixed>
     */
    public function loadSession(User $actor, string $sessionUuid): array
    {
        $thread = $this->resolveThread($actor, $sessionUuid);
        $channel = $this->resolveThreadChannel($thread);
        $messages = $thread->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn (Message $message): array => $this->messagePayload($message))
            ->values()
            ->all();

        return [
            ...$this->sessionPayload($thread, $channel),
            'messages' => $messages,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function promptSession(User $actor, string $sessionUuid, ?string $channelUuid, string $text): array
    {
        $thread = $this->resolveThread($actor, $sessionUuid);
        $channel = $this->resolveThreadChannel($thread, $channelUuid);

        Gate::forUser($actor)->authorize('create', Message::class);

        $text = $this->trimmedString($text);
        abort_if($text === null, 422, 'A text prompt is required.');

        $this->markActiveThread($channel, $actor, $thread);

        $dispatch = $this->dispatchPromptOperation->run(
            channel: $channel,
            thread: $thread,
            actor: $actor,
            text: $text,
            options: [
                'agent_source' => 'acp_prompt',
                'direct_source' => 'acp_prompt',
                'dispatch_observers_when_agent' => false,
                'dispatch_observers_when_direct' => false,
                'ensure_membership' => true,
                'ensure_presenter' => true,
                'presenter_actor_type' => $this->defaultHandlerActor($thread->purpose ?: Thread::PurposeExecution),
                'meta' => [
                    'acp' => [
                        'owner' => [
                            'subject_type' => $actor->getMorphClass(),
                            'subject_id' => $actor->getKey(),
                        ],
                        'channel_uuid' => $channel->uuid,
                        'thread_uuid' => $thread->uuid,
                        'requested_at' => now()->toIso8601String(),
                    ],
                ],
            ],
        );
        $promptMessage = $dispatch['message'];

        $promptMeta = is_array($promptMessage->meta) ? $promptMessage->meta : [];
        $task = $this->agentTaskService->createLocalTask(
            promptMessage: $promptMessage,
            user: $actor,
            payload: [
                'local' => [
                    'protocol' => 'acp',
                    'owner' => [
                        'subject_type' => $actor->getMorphClass(),
                        'subject_id' => $actor->getKey(),
                    ],
                ],
            ],
        );
        $promptMeta['acp'] = [
            ...(is_array($promptMeta['acp'] ?? null) ? $promptMeta['acp'] : []),
            'task_id' => $task->uuid,
        ];

        $promptMessage->forceFill([
            'meta' => $promptMeta,
        ])->save();

        $task = $this->agentTaskService->syncLocalTask($task);

        return $this->taskPayload($task);
    }

    /**
     * @return array<string, mixed>
     */
    public function task(User $actor, string $taskId): array
    {
        $task = $this->agentTaskService->resolveOwnedAcpTask($actor, $taskId);
        abort_unless($task instanceof AgentTask, 404);

        return $this->taskPayload($this->agentTaskService->syncLocalTask($task));
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelTask(User $actor, string $taskId): array
    {
        $task = $this->agentTaskService->resolveOwnedAcpTask($actor, $taskId);
        abort_unless($task instanceof AgentTask, 404);

        $promptMessage = $task->message;
        abort_unless($promptMessage instanceof Message, 404);
        $thread = $this->messageTaskService->resolveMessageThread($promptMessage);

        if (! $thread instanceof Thread) {
            return $this->taskPayload($task);
        }

        $task = $this->agentTaskService->cancelLocalTask(
            task: $task,
            presenters: $this->resolveActiveThreadPresenters->execute($thread),
            canceledMetaPath: 'acp.canceled_at',
        );

        return $this->taskPayload($task);
    }

    protected function visibleChannelsQuery(User $actor): Builder
    {
        Gate::forUser($actor)->authorize('viewAny', Channel::class);

        $query = Channel::query()->latest('created_at');

        $query->whereHas('actorStates', function (Builder $builder) use ($actor): void {
            $builder
                ->where('actorable_type', $actor->getMorphClass())
                ->where('actorable_id', $actor->getKey())
                ->where('status', ChannelActorState::StatusActive);
        });

        return $query;
    }

    protected function resolveThread(User $actor, string $sessionUuid): Thread
    {
        $thread = Thread::query()
            ->where('uuid', $sessionUuid)
            ->firstOrFail();

        Gate::forUser($actor)->authorize('view', $thread);

        return $thread;
    }

    protected function resolveThreadChannel(Thread $thread, ?string $channelUuid = null): Channel
    {
        $channel = $thread->threadable;
        abort_unless($channel instanceof Channel, 404, 'Thread channel was not found.');

        if ($channelUuid !== null && $channel->uuid !== $channelUuid) {
            abort(404, 'Thread channel was not found.');
        }

        return $channel;
    }

    protected function markActiveThread(Channel $channel, User $actor, Thread $thread): void
    {
        ChannelActorState::query()->updateOrCreate(
            [
                'channel_id' => $channel->getKey(),
                'actorable_type' => $actor->getMorphClass(),
                'actorable_id' => $actor->getKey(),
            ],
            [
                'thread_id' => $thread->getKey(),
                'status' => ChannelActorState::StatusActive,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function sessionPayload(Thread $thread, Channel $channel): array
    {
        return [
            'id' => $thread->uuid,
            'title' => $thread->title ?: 'Thread',
            'purpose' => $thread->purpose,
            'status' => $thread->status,
            'channel' => [
                'id' => $channel->uuid,
                'status' => $channel->status,
            ],
            'created_at' => optional($thread->created_at)?->toIso8601String(),
            'last_message_at' => $this->messageTimestamp($thread),
        ];
    }

    protected function messageTimestamp(Thread $thread): ?string
    {
        $latestMessage = $thread->messages()
            ->latest('created_at')
            ->first();

        return optional($latestMessage?->created_at)?->toIso8601String()
            ?? optional($thread->created_at)?->toIso8601String();
    }

    /**
     * @return array<string, mixed>
     */
    protected function messagePayload(Message $message): array
    {
        return [
            'id' => $message->id,
            'role' => $this->messageRole($message),
            'text' => is_string($message->text) ? $message->text : '',
            'source' => data_get($message->meta, 'source'),
            'created_at' => optional($message->created_at)?->toIso8601String(),
        ];
    }

    protected function messageRole(Message $message): string
    {
        if (data_get($message->meta, 'source') === 'agent_response' || $message->senderable_type === null) {
            return 'assistant';
        }

        return 'user';
    }

    /**
     * @return array<string, mixed>
     */
    protected function taskPayload(AgentTask $task): array
    {
        $promptMessage = $task->message;
        abort_unless($promptMessage instanceof Message, 404);

        $task = $this->agentTaskService->syncLocalTask($task);
        $snapshot = $this->messageTaskService->snapshot($promptMessage);
        $thread = $snapshot['thread'];
        $channel = $snapshot['channel'];
        $assistantReplies = $snapshot['assistant_replies'];
        $invocations = $snapshot['invocations'];

        return [
            'id' => $this->agentTaskService->publicId($task),
            'kind' => 'task',
            'state' => $task->status,
            'session_id' => $thread?->uuid,
            'channel_id' => $channel?->uuid,
            'prompt' => [
                'id' => $promptMessage->id,
                'text' => is_string($promptMessage->text) ? $promptMessage->text : '',
                'created_at' => optional($promptMessage->created_at)?->toIso8601String(),
            ],
            'invocations' => $this->messageTaskService->invocationPayload($invocations),
            'artifacts' => $assistantReplies
                ->map(fn (Message $message): array => $this->messageTaskService->basicArtifactPayload($message))
                ->values()
                ->all(),
        ];
    }

    protected function resolvedPurpose(?string $purpose): string
    {
        $purpose = $this->trimmedString($purpose);

        return in_array($purpose, [
            Thread::PurposeMain,
            Thread::PurposePlanning,
            Thread::PurposeExecution,
            Thread::PurposeBilling,
            Thread::PurposeDispute,
            Thread::PurposeSupport,
            Thread::PurposeSystem,
        ], true)
            ? $purpose
            : Thread::PurposeExecution;
    }

    protected function defaultHandlerActor(string $purpose): string
    {
        return match ($purpose) {
            Thread::PurposeExecution, Thread::PurposeBilling => ThreadActor::ActorOrderAgent,
            default => ThreadActor::ActorRequestAgent,
        };
    }

    protected function defaultTitle(string $purpose): string
    {
        return match ($purpose) {
            Thread::PurposePlanning => 'Planning',
            Thread::PurposeExecution => 'Execution',
            Thread::PurposeBilling => 'Billing',
            Thread::PurposeDispute => 'Dispute',
            Thread::PurposeSupport => 'Support',
            Thread::PurposeSystem => 'System',
            default => 'Project Main',
        };
    }

    protected function defaultPhase(string $purpose): string
    {
        return match ($purpose) {
            Thread::PurposePlanning => 'scope_planning',
            Thread::PurposeExecution => 'order_kickoff',
            Thread::PurposeBilling => 'billing_review',
            Thread::PurposeDispute => 'opened',
            Thread::PurposeSupport => 'support_open',
            Thread::PurposeSystem => 'system_open',
            default => 'request_intake',
        };
    }

    protected function trimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
