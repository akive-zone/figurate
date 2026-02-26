<?php

namespace App\Ai\Support;

use App\Actions\Server\Chat\StoreThreadMessage;
use App\Ai\Agents\PresenterAgent;
use App\Ai\Storage\ConversationId;
use App\Ai\Storage\ConversationPersistenceResolver;
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
                ->then(function (StreamableAgentResponse $response) use ($thread, $userMessage, $userId, $threadActor): void {
                    $this->handleQueuedThreadActorReplySuccess(
                        threadId: $thread->id,
                        userMessageId: $userMessage->id,
                        userId: $userId,
                        threadActorId: $threadActor->id,
                        response: $response,
                    );
                })
                ->catch(function (Throwable $exception): void {
                    report($exception);
                });
        } catch (Throwable $exception) {
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

        ($this->storeThreadMessage)(
            thread: $thread,
            sender: null,
            body: $assistantText,
            meta: [
                'source' => 'agent_response',
                'actor_key' => $threadActor->actorName(),
                'conversation_id' => $response->conversationId ?? $session->conversation_id,
                'in_reply_to_message_id' => $userMessage->id,
            ],
        );
    }
}
