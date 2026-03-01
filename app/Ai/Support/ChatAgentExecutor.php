<?php

namespace App\Ai\Support;

use App\Actions\Server\Chat\StoreThreadMessage;
use App\Ai\Agents\PresenterAgent;
use App\Ai\Storage\ConversationId;
use App\Ai\Storage\ConversationPersistenceResolver;
use App\Models\Server\AgentConversationMessage;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\ThreadActorSession;
use App\Models\Server\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Throwable;

class ChatAgentExecutor
{
    public function __construct(
        protected StoreThreadMessage $storeThreadMessage,
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

        $content = is_string($userMessage->body) ? trim($userMessage->body) : '';

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

        if ($assistantText === '') {
            return;
        }

        $assistantMessage = ($this->storeThreadMessage)(
            thread: $thread,
            sender: null,
            body: $assistantText,
            meta: [
                'source' => 'agent_response',
                'actor_key' => $threadActor->actorName(),
                'conversation_id' => $response->conversationId ?? $session->conversation_id,
                'in_reply_to_message_id' => $userMessage->id,
                'invocation_id' => $response->invocationId,
            ],
        );

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

        $this->markPromptInvocationState(
            userMessage: $userMessage,
            threadActor: $threadActor,
            status: 'failed',
            errorMessage: $exception->getMessage(),
        );
    }
}
