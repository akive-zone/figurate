<?php

namespace App\Ai\Support;

use App\Ai\Agents\OrderAgent;
use App\Ai\Agents\RequestAgent;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\ThreadActorMemory;
use App\Models\Server\User;
use Illuminate\Broadcasting\PrivateChannel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Throwable;

class ChatAgentExecutor
{
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

        $memory = $this->resolveThreadActorMemory($thread, $threadActor);
        $handler = $this->resolveThreadActorHandler($threadActor, $user);

        if ($memory->conversation_id) {
            $handler->continue($memory->conversation_id, $user);
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
                ->then(function (StreamableAgentResponse $response) use ($thread, $userMessage, $threadActor): void {
                    $this->handleQueuedThreadActorReplySuccess(
                        threadId: $thread->id,
                        userMessageId: $userMessage->id,
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

    protected function resolveThreadActorMemory(Thread $thread, ThreadActor $threadActor): ThreadActorMemory
    {
        return ThreadActorMemory::query()->firstOrCreate(
            [
                'thread_id' => $thread->id,
                'thread_actor_id' => $threadActor->id,
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

        return match ($threadActor->actorName()) {
            ThreadActor::ActorOrderAgent => OrderAgent::make(thread: $thread, actor: $user),
            default => RequestAgent::make(thread: $thread, actor: $user),
        };
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

        $memory = $this->resolveThreadActorMemory($thread, $threadActor);

        if ($response->conversationId) {
            $memory->forceFill([
                'conversation_id' => $response->conversationId,
                'last_used_at' => now(),
            ])->save();
        }

        $assistantText = is_string($response->text) ? trim($response->text) : '';

        if ($assistantText === '') {
            return;
        }

        $thread->messages()->create([
            'senderable_type' => null,
            'senderable_id' => null,
            'type' => 'text',
            'body' => $assistantText,
            'attachments' => null,
            'meta' => [
                'source' => 'agent_response',
                'actor_key' => $threadActor->actorName(),
                'conversation_id' => $response->conversationId ?? $memory->conversation_id,
                'in_reply_to_message_id' => $userMessage->id,
            ],
        ]);
    }
}
