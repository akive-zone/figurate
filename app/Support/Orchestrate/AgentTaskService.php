<?php

namespace App\Support\Orchestrate;

use App\Models\Server\AgentTask;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use RuntimeException;

class AgentTaskService
{
    public function __construct(
        protected MessageTaskService $messageTaskService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createLocalTask(
        Message $promptMessage,
        ?User $user = null,
        array $payload = [],
        ?string $stateOverride = null,
    ): AgentTask {
        $thread = $this->resolvePromptThread($promptMessage);
        $task = AgentTask::query()->firstOrNew([
            'message_id' => $promptMessage->getKey(),
        ]);

        $task->forceFill([
            'thread_id' => $thread->getKey(),
            'message_id' => $promptMessage->getKey(),
            'user_id' => $user?->getKey(),
            'remote' => null,
            'last_payload' => $this->mergedLocalPayload($task, $payload),
        ])->save();

        return $this->syncLocalTask($task, $stateOverride);
    }

    public function syncLocalTask(AgentTask $task, ?string $stateOverride = null): AgentTask
    {
        if ($task->remote !== null) {
            return $task;
        }

        $promptMessage = $task->message;
        if (! $promptMessage instanceof Message) {
            return $task;
        }

        $snapshot = $this->messageTaskService->snapshot($promptMessage);
        $state = $stateOverride ?? $snapshot['state'];
        $payload = is_array($task->last_payload) ? $task->last_payload : [];
        $localPayload = is_array($payload['local'] ?? null) ? $payload['local'] : [];
        $publicId = $this->publicId($task);

        $payload['local'] = [
            ...$localPayload,
            'task_uuid' => $task->uuid,
            'public_id' => $publicId,
            'prompt_message_id' => $promptMessage->getKey(),
            'prompt_message_ulid' => $promptMessage->ulid,
            'thread_id' => $snapshot['thread']?->uuid,
            'channel_id' => $snapshot['channel']?->uuid,
        ];
        $payload['snapshot'] = [
            'state' => $state,
            'invocations' => $this->messageTaskService->invocationPayload($snapshot['invocations']),
            'artifacts' => $snapshot['assistant_replies']
                ->map(fn (Message $message): array => $this->messageTaskService->basicArtifactPayload($message))
                ->values()
                ->all(),
            'updated_at' => now()->toIso8601String(),
        ];

        $task->forceFill([
            'status' => $state,
            'last_payload' => $payload,
            'completed_at' => $state === 'completed' ? ($task->completed_at ?? now()) : $task->completed_at,
            'failed_at' => $state === 'failed' ? ($task->failed_at ?? now()) : $task->failed_at,
            'canceled_at' => $state === 'canceled' ? ($task->canceled_at ?? now()) : $task->canceled_at,
        ])->save();

        return $task->fresh();
    }

    public function syncLocalTaskForPromptMessage(Message $promptMessage, ?string $stateOverride = null): ?AgentTask
    {
        $task = $this->localTasksQuery()
            ->where('message_id', $promptMessage->getKey())
            ->latest('id')
            ->first();

        if (! $task instanceof AgentTask) {
            return null;
        }

        return $this->syncLocalTask($task, $stateOverride);
    }

    public function resolveOwnedAcpTask(User $actor, string $taskId): ?AgentTask
    {
        return $this->localTasksQuery()
            ->where('user_id', $actor->getKey())
            ->latest('id')
            ->get()
            ->first(fn (AgentTask $task): bool => $task->uuid === $taskId || $this->publicId($task) === $taskId);
    }

    /**
     * @param  array{subject_type: string, subject_id: int|string, token_id?: int|null}|null  $owner
     */
    public function resolveOwnedA2aTask(string $taskId, ?array $owner): ?AgentTask
    {
        if (! is_array($owner)) {
            return null;
        }

        return $this->localTasksQuery()
            ->latest('id')
            ->get()
            ->first(function (AgentTask $task) use ($owner, $taskId): bool {
                if ($task->uuid !== $taskId && $this->publicId($task) !== $taskId) {
                    return false;
                }

                return $this->matchesOwner($task, $owner);
            });
    }

    /**
     * @param  array{subject_type: string, subject_id: int|string, token_id?: int|null}|null  $owner
     * @return Collection<int, AgentTask>
     */
    public function listOwnedA2aTasks(?array $owner, ?string $userUuid, int $limit): Collection
    {
        if (! is_array($owner)) {
            return collect();
        }

        return $this->localTasksQuery()
            ->latest('id')
            ->get()
            ->filter(function (AgentTask $task) use ($owner, $userUuid): bool {
                if (! $this->matchesOwner($task, $owner)) {
                    return false;
                }

                if (! is_string($userUuid) || trim($userUuid) === '') {
                    return true;
                }

                return $task->user?->uuid === trim($userUuid);
            })
            ->take($limit)
            ->values();
    }

    /**
     * @param  Collection<int, \App\Models\Server\ThreadActor>  $presenters
     */
    public function cancelLocalTask(AgentTask $task, Collection $presenters, ?string $canceledMetaPath = null): AgentTask
    {
        $promptMessage = $task->message;

        if (! $promptMessage instanceof Message) {
            return $task;
        }

        $this->messageTaskService->cancelPrompt(
            promptMessage: $promptMessage,
            presenters: $presenters,
            canceledMetaPath: $canceledMetaPath,
        );

        return $this->syncLocalTask($task);
    }

    public function publicId(AgentTask $task): string
    {
        $publicId = data_get($task->last_payload, 'local.public_id');

        if (is_string($publicId) && trim($publicId) !== '') {
            return trim($publicId);
        }

        return $task->uuid;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function mergedLocalPayload(AgentTask $task, array $payload): array
    {
        $existing = is_array($task->last_payload) ? $task->last_payload : [];
        $existingLocal = is_array($existing['local'] ?? null) ? $existing['local'] : [];
        $incomingLocal = is_array($payload['local'] ?? null) ? $payload['local'] : [];

        return [
            ...$existing,
            ...$payload,
            'local' => [
                ...$existingLocal,
                ...$incomingLocal,
            ],
        ];
    }

    protected function resolvePromptThread(Message $promptMessage): Thread
    {
        $thread = $this->messageTaskService->resolveMessageThread($promptMessage);

        if (! $thread instanceof Thread) {
            throw new RuntimeException('Prompt message must belong to a thread.');
        }

        return $thread;
    }

    protected function localTasksQuery(): Builder
    {
        return AgentTask::query()->whereNull('remote');
    }

    /**
     * @param  array{subject_type: string, subject_id: int|string, token_id?: int|null}  $owner
     */
    protected function matchesOwner(AgentTask $task, array $owner): bool
    {
        $taskOwner = data_get($task->last_payload, 'local.owner');

        return is_array($taskOwner)
            && ($taskOwner['subject_type'] ?? null) === $owner['subject_type']
            && (string) ($taskOwner['subject_id'] ?? '') === (string) $owner['subject_id'];
    }
}
