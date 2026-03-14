<?php

namespace App\Ai\Support;

use App\A2a\TaskPushNotificationDispatcher;
use App\Actions\Server\Chat\DispatchThreadMessage;
use App\Actions\Server\Chat\ThreadMessageEntry;
use App\Ai\Agents\PresenterAgent;
use App\Ai\Storage\ConversationId;
use App\Ai\Storage\ConversationPersistenceResolver;
use App\Models\Server\AgentConversationMessage;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\ThreadActorSession;
use App\Models\Server\User;
use App\Support\Orchestrate\AgentTaskService;
use App\Support\Orchestrate\MessageTaskService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Throwable;

class ChatAgentExecutor
{
    public function __construct(
        protected DispatchThreadMessage $dispatchThreadMessage,
        protected TaskPushNotificationDispatcher $taskPushNotificationDispatcher,
        protected AgentTaskService $agentTaskService,
        protected MessageTaskService $messageTaskService,
    ) {}

    public function queue(
        Thread $thread,
        Message $userMessage,
        User $user,
        ThreadActor $threadActor,
        string $broadcastChannelId
    ): void {
        if (
            $threadActor->thread_id !== $thread->id ||
            $userMessage->messageable_type !== $thread->getMorphClass() ||
            $userMessage->messageable_id !== $thread->getKey()
        ) {
            return;
        }

        $existingAssistantMessage = $this->findAssistantReplyForThreadActor($thread, $userMessage, $threadActor);

        if ($existingAssistantMessage) {
            return;
        }

        if ($this->isInvocationCanceled($userMessage, $threadActor)) {
            return;
        }

        $content = is_string($userMessage->text) ? trim($userMessage->text) : '';

        if ($content === '') {
            return;
        }

        $userId = $user->id;
        $session = $this->resolveThreadActorSession($thread, $threadActor, $userId);
        $handler = $this->resolveThreadActorHandler($threadActor, $user);
        $this->markPromptInvocationState(
            userMessage: $userMessage,
            threadActor: $threadActor,
            status: 'pending',
        );

        if ($session->conversation_id) {
            $handler->continue($session->conversation_id, $user);
        } else {
            $handler->forUser($user);
        }

        try {
            $queuedResponse = $handler->broadcastOnQueue(
                $content,
                [new PrivateChannel($broadcastChannelId)],
            );

            $queuedResponse->afterCommit();
            $queuedResponse
                ->then(function (AgentResponse|StreamableAgentResponse $response) use ($thread, $userMessage, $userId, $threadActor): void {
                    $this->handleQueuedThreadActorReplySuccess(
                        threadId: $thread->id,
                        userMessageId: $userMessage->id,
                        userId: $userId,
                        threadActorId: $threadActor->id,
                        response: $response,
                    );
                })
                ->catch(function (Throwable $exception) use ($thread, $userMessage, $threadActor): void {
                    $this->handleQueuedThreadActorReplyFailure(
                        threadId: $thread->id,
                        userMessageId: $userMessage->id,
                        threadActorId: $threadActor->id,
                        exception: $exception,
                    );
                    report($exception);
                });
        } catch (Throwable $exception) {
            $this->markPromptInvocationState(
                userMessage: $userMessage,
                threadActor: $threadActor,
                status: 'failed',
                errorMessage: $exception->getMessage(),
            );
            report($exception);
        }
    }

    protected function resolveThreadActorSession(Thread $thread, ThreadActor $threadActor, ?int $userId): ThreadActorSession
    {
        return ThreadActorSession::query()->firstOrCreate(
            [
                'thread_id' => $thread->id,
                'thread_actor_id' => $threadActor->id,
                'user_id' => $userId,
                'provider' => 'default',
                'model' => 'default',
            ],
            [
                'conversation_id' => null,
                'state' => null,
                'last_used_at' => null,
            ],
        );
    }

    protected function resolveThreadActorHandler(ThreadActor $threadActor, User $user): Agent
    {
        $thread = $threadActor->thread;
        $conversationPersistenceMode = $this->requestedConversationPersistenceMode();

        $agent = match ($threadActor->actorName()) {
            default => PresenterAgent::make(
                thread: $thread,
                actor: $user,
            ),
        };

        if (method_exists($agent, 'setPresenterActorKey')) {
            $agent->setPresenterActorKey($threadActor->actorName());
        }

        if ($conversationPersistenceMode !== null && method_exists($agent, 'setConversationMode')) {
            $agent->setConversationMode($conversationPersistenceMode);
        }

        return $agent;
    }

    protected function requestedConversationPersistenceMode(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        return ConversationPersistenceResolver::normalizeMode(
            $request?->input('conversation_persistence')
            ?? $request?->header('X-Conversation-Persistence')
        );
    }

    protected function findAssistantReplyForThreadActor(
        Thread $thread,
        Message $userMessage,
        ThreadActor $threadActor
    ): ?Message {
        return Message::query()
            ->where('messageable_type', $thread->getMorphClass())
            ->where('messageable_id', $thread->getKey())
            ->whereNull('senderable_type')
            ->whereNull('senderable_id')
            ->where('meta->source', 'agent_response')
            ->where('meta->actor_key', $threadActor->actorName())
            ->where('meta->in_reply_to_message_id', $userMessage->id)
            ->oldest('id')
            ->first();
    }

    protected function handleQueuedThreadActorReplySuccess(
        int $threadId,
        int $userMessageId,
        int $userId,
        int $threadActorId,
        AgentResponse|StreamableAgentResponse $response
    ): void {
        $thread = Thread::query()->find($threadId);
        $userMessage = Message::query()->find($userMessageId);
        $threadActor = ThreadActor::query()->find($threadActorId);

        if (! $thread || ! $userMessage || ! $threadActor) {
            return;
        }

        if (
            $threadActor->thread_id !== $thread->id ||
            $userMessage->messageable_type !== $thread->getMorphClass() ||
            $userMessage->messageable_id !== $thread->getKey()
        ) {
            return;
        }

        $existingAssistantMessage = $this->findAssistantReplyForThreadActor($thread, $userMessage, $threadActor);

        if ($existingAssistantMessage) {
            return;
        }

        if ($this->isInvocationCanceled($userMessage, $threadActor)) {
            return;
        }

        $session = $this->resolveThreadActorSession($thread, $threadActor, $userId);

        $this->markPromptInvocationState(
            userMessage: $userMessage,
            threadActor: $threadActor,
            status: 'completed',
            invocationId: $response->invocationId ?? null,
            conversationId: $response->conversationId ?? null,
        );

        if ($response->conversationId) {
            $storageConversationId = ConversationId::toStorageId($response->conversationId);

            if (! DB::table('agent_conversations')->where('id', $storageConversationId)->exists()) {
                DB::table('agent_conversations')->insert([
                    'id' => $storageConversationId,
                    'user_id' => $userId,
                    'title' => mb_substr($response->conversationId, 0, 255),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $session->forceFill([
                'conversation_id' => $storageConversationId,
                'last_used_at' => now(),
            ])->save();
        }

        $assistantText = is_string($response->text) ? trim($response->text) : '';
        [$assistantText, $assistantA2ui] = $this->extractAssistantA2uiPayload($assistantText);

        if ($assistantText === '' && ! is_array($assistantA2ui)) {
            return;
        }

        $assistantMessage = ($this->dispatchThreadMessage)(ThreadMessageEntry::agentMessage(
            thread: $thread,
            text: $assistantText !== '' ? $assistantText : 'Interactive step ready.',
            meta: [
                'actor_key' => $threadActor->actorName(),
                'conversation_id' => $response->conversationId ?? $session->conversation_id,
                'in_reply_to_message_id' => $userMessage->id,
                'invocation_id' => $response->invocationId,
                'a2ui' => is_array($assistantA2ui) ? $assistantA2ui : null,
            ],
            source: 'agent_response',
        ));

        $this->agentTaskService->syncLocalTaskForPromptMessage($userMessage);
        $this->linkAgentTelemetryToThreadMessages($thread, $userMessage, $assistantMessage, $threadActor, $userId, $response);
    }

    protected function markPromptInvocationState(
        Message $userMessage,
        ThreadActor $threadActor,
        string $status,
        ?string $invocationId = null,
        ?string $conversationId = null,
        ?string $errorMessage = null,
    ): void {
        $promptMeta = is_array($userMessage->meta) ? $userMessage->meta : [];
        $invocations = is_array($promptMeta['invocations'] ?? null) ? $promptMeta['invocations'] : [];
        $actorKey = $threadActor->actorName();

        if (! is_string($actorKey) || $actorKey === '') {
            $actorKey = ThreadActor::ActorRequestAgent;
        }

        $existing = is_array($invocations[$actorKey] ?? null) ? $invocations[$actorKey] : [];
        $resolvedConversationId = is_string($conversationId) && trim($conversationId) !== ''
            ? $conversationId
            : (is_string($existing['conversation_id'] ?? null) ? $existing['conversation_id'] : null);
        $resolvedInvocationId = is_string($invocationId) && trim($invocationId) !== ''
            ? $invocationId
            : (is_string($existing['invocation_id'] ?? null) ? $existing['invocation_id'] : null);

        $invocations[$actorKey] = [
            ...$existing,
            'status' => $status,
            'invocation_id' => $resolvedInvocationId,
            'conversation_id' => $resolvedConversationId,
            'conversation_storage_id' => $resolvedConversationId
                ? ConversationId::toStorageId($resolvedConversationId)
                : null,
            'recorded_at' => now()->toIso8601String(),
        ];

        if ($status === 'failed') {
            $invocations[$actorKey]['error_message'] = is_string($errorMessage) ? mb_substr(trim($errorMessage), 0, 400) : null;
            $invocations[$actorKey]['failed_at'] = now()->toIso8601String();
        }

        $promptMeta['invocations'] = $invocations;

        $userMessage->forceFill([
            'meta' => $promptMeta,
        ])->save();
        $this->agentTaskService->syncLocalTaskForPromptMessage($userMessage);

        if (is_string(data_get($promptMeta, 'a2a_task_id')) && trim((string) data_get($promptMeta, 'a2a_task_id')) !== '') {
            $this->taskPushNotificationDispatcher->dispatchTaskUpdate(
                promptMessage: $userMessage,
                state: $this->messageTaskService->resolveTaskState($invocations),
            );
        }
    }

    protected function linkAgentTelemetryToThreadMessages(
        Thread $thread,
        Message $userMessage,
        Message $assistantMessage,
        ThreadActor $threadActor,
        int $userId,
        AgentResponse $response
    ): void {
        if (! is_string($response->conversationId) || trim($response->conversationId) === '') {
            return;
        }

        $storageConversationId = ConversationId::toStorageId($response->conversationId);
        $rows = AgentConversationMessage::query()
            ->where('conversation_id', $storageConversationId)
            ->where('user_id', $userId)
            ->where('agent', PresenterAgent::class)
            ->where('role', 'assistant')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $telemetry = $rows->first(function (AgentConversationMessage $message) use ($response): bool {
            $meta = json_decode((string) $message->meta, true);

            return is_array($meta) && ($meta['invocation_id'] ?? null) === $response->invocationId;
        });

        if (! $telemetry instanceof AgentConversationMessage) {
            return;
        }

        $meta = json_decode((string) $telemetry->meta, true);
        $meta = is_array($meta) ? $meta : [];
        $actorKey = $threadActor->actorName();

        if (! is_string($actorKey) || $actorKey === '') {
            $actorKey = ThreadActor::ActorRequestAgent;
        }

        $meta['thread_id'] = $thread->id;
        $meta['thread_uuid'] = $thread->uuid;
        $meta['thread_message_id'] = $assistantMessage->id;
        $meta['in_reply_to_message_id'] = $userMessage->id;
        $meta['actor_key'] = $actorKey;

        $telemetry->forceFill([
            'meta' => json_encode($meta),
        ])->save();
    }

    protected function handleQueuedThreadActorReplyFailure(
        int $threadId,
        int $userMessageId,
        int $threadActorId,
        Throwable $exception
    ): void {
        $thread = Thread::query()->find($threadId);
        $userMessage = Message::query()->find($userMessageId);
        $threadActor = ThreadActor::query()->find($threadActorId);

        if (! $thread || ! $userMessage || ! $threadActor) {
            return;
        }

        if (
            $threadActor->thread_id !== $thread->id ||
            $userMessage->messageable_type !== $thread->getMorphClass() ||
            $userMessage->messageable_id !== $thread->getKey()
        ) {
            return;
        }

        if ($this->isInvocationCanceled($userMessage, $threadActor)) {
            return;
        }

        $this->markPromptInvocationState(
            userMessage: $userMessage,
            threadActor: $threadActor,
            status: 'failed',
            errorMessage: $exception->getMessage(),
        );
    }

    protected function isInvocationCanceled(Message $userMessage, ThreadActor $threadActor): bool
    {
        $actorKey = $threadActor->actorName();

        if (! is_string($actorKey) || $actorKey === '') {
            $actorKey = ThreadActor::ActorRequestAgent;
        }

        $status = data_get($userMessage->meta, "invocations.{$actorKey}.status");

        return is_string($status) && $status === 'canceled';
    }

    /**
     * @return array{0: string, 1: array<string, mixed>|null}
     */
    protected function extractAssistantA2uiPayload(string $assistantText): array
    {
        $decoded = $this->decodePossibleJsonObject($assistantText);

        if (! is_array($decoded)) {
            return [$assistantText, null];
        }

        $payload = is_array($decoded['a2ui'] ?? null) ? $decoded['a2ui'] : null;

        if (! is_array($payload) && $this->looksLikeA2uiPayload($decoded)) {
            $payload = $decoded;
        }

        if (! is_array($payload)) {
            return [$assistantText, null];
        }

        $resolvedText = $this->resolvedAssistantTextFromDecodedPayload($decoded, $assistantText);
        $payload = $this->normalizeAssistantA2uiPayload($payload);

        if (! is_array($payload)) {
            return [$assistantText, null];
        }

        return [$resolvedText, $payload];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodePossibleJsonObject(string $assistantText): ?array
    {
        $trimmed = trim($assistantText);

        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (! preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $trimmed, $matches)) {
            return null;
        }

        $fencedDecoded = json_decode((string) ($matches[1] ?? ''), true);

        return is_array($fencedDecoded) ? $fencedDecoded : null;
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    protected function looksLikeA2uiPayload(array $decoded): bool
    {
        return array_key_exists('surfaceUpdate', $decoded)
            || array_key_exists('dataModelUpdate', $decoded)
            || array_key_exists('beginRendering', $decoded)
            || array_key_exists('deleteSurface', $decoded);
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    protected function resolvedAssistantTextFromDecodedPayload(array $decoded, string $assistantText): string
    {
        $candidates = [
            $decoded['text'] ?? null,
            $decoded['message'] ?? null,
            data_get($decoded, 'a2ui.text'),
            data_get($decoded, 'a2ui.message'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $trimmed = trim($candidate);

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return $this->looksLikeA2uiPayload($decoded) || is_array($decoded['a2ui'] ?? null)
            ? ''
            : $assistantText;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    protected function normalizeAssistantA2uiPayload(array $payload): ?array
    {
        $normalized = $payload;
        $hasRecognizedKey = false;

        foreach (['beginRendering', 'surfaceUpdate'] as $key) {
            if (! is_array($normalized[$key] ?? null)) {
                continue;
            }

            $surface = $this->normalizeAssistantA2uiSurface($normalized[$key]);

            if ($surface === null) {
                unset($normalized[$key]);

                continue;
            }

            $normalized[$key] = $surface;
            $hasRecognizedKey = true;
        }

        if (is_array($normalized['dataModelUpdate'] ?? null) || is_string($normalized['dataModelUpdate'] ?? null)) {
            $hasRecognizedKey = true;
        }

        if (is_string($normalized['deleteSurface'] ?? null) && trim($normalized['deleteSurface']) !== '') {
            $normalized['deleteSurface'] = trim($normalized['deleteSurface']);
            $hasRecognizedKey = true;
        }

        if (! $hasRecognizedKey && $this->looksLikeAssistantSurface($normalized)) {
            $surface = $this->normalizeAssistantA2uiSurface($normalized);

            if ($surface === null) {
                return null;
            }

            return ['beginRendering' => $surface];
        }

        return $hasRecognizedKey ? $normalized : null;
    }

    /**
     * @param  array<string, mixed>  $surface
     * @return array<string, mixed>|null
     */
    protected function normalizeAssistantA2uiSurface(array $surface): ?array
    {
        $surfaceId = is_string($surface['id'] ?? null) ? trim($surface['id']) : '';

        if ($surfaceId === '') {
            $surfaceId = is_string($surface['surfaceId'] ?? null) ? trim($surface['surfaceId']) : '';
        }

        if ($surfaceId === '') {
            $surfaceId = 'surface_'.substr(sha1(json_encode($surface)), 0, 12);
        }

        $fields = is_array($surface['fields'] ?? null) ? $surface['fields'] : [];
        $actions = is_array($surface['actions'] ?? null) ? $surface['actions'] : [];

        $normalizedActions = collect($actions)
            ->map(fn (mixed $action, int $index): ?array => is_array($action) ? $this->normalizeAssistantA2uiAction($action, $index, $surfaceId) : null)
            ->filter(fn (mixed $action): bool => is_array($action))
            ->values()
            ->all();

        if ($normalizedActions === [] && $fields !== []) {
            $normalizedActions = [[
                'id' => "{$surfaceId}_submit",
                'name' => 'submit',
                'type' => 'submit',
                'label' => 'Submit',
            ]];
        }

        $normalized = $surface;
        $normalized['id'] = $surfaceId;
        $normalized['actions'] = $normalizedActions;

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>|null
     */
    protected function normalizeAssistantA2uiAction(array $action, int $index, string $surfaceId): ?array
    {
        $name = is_string($action['name'] ?? null) && trim($action['name']) !== ''
            ? trim($action['name'])
            : null;
        $type = is_string($action['type'] ?? null) && trim($action['type']) !== ''
            ? trim($action['type'])
            : null;
        $id = is_string($action['id'] ?? null) && trim($action['id']) !== ''
            ? trim($action['id'])
            : null;

        if ($id === null) {
            $id = "{$surfaceId}_action_".($index + 1);
        }

        if ($name === null && $type === null) {
            $name = 'submit';
            $type = 'submit';
        }

        $sourceComponentId = is_string($action['sourceComponentId'] ?? null) && trim($action['sourceComponentId']) !== ''
            ? trim($action['sourceComponentId'])
            : null;

        return array_filter([
            ...$action,
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'sourceComponentId' => $sourceComponentId,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function looksLikeAssistantSurface(array $payload): bool
    {
        return is_array($payload['fields'] ?? null)
            || is_array($payload['actions'] ?? null)
            || is_array(data_get($payload, 'schema.fields'))
            || (is_string($payload['title'] ?? null) && trim($payload['title']) !== '')
            || (is_string($payload['question'] ?? null) && trim($payload['question']) !== '');
    }
}
