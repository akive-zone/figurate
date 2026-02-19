<?php

namespace App\Jobs;

use App\Ai\Agents\OrderAgent;
use App\Ai\Agents\RequestAgent;
use App\Events\AgentReplyCompleted;
use App\Events\AgentReplyFailed;
use App\Events\AgentReplyStarted;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\ThreadActorMemory;
use App\Models\Server\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\HttpClientException;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

class GenerateAgentReply implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public int $threadId,
        public int $userMessageId,
        public int $actorId,
        public int $primaryPresenterActorId,
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $thread = Thread::query()->find($this->threadId);
        $userMessage = Message::query()->find($this->userMessageId);
        $actor = User::query()->find($this->actorId);
        $primaryPresenter = ThreadActor::query()->find($this->primaryPresenterActorId);

        if (! $thread || ! $userMessage || ! $actor || ! $primaryPresenter) {
            return;
        }

        if (
            $primaryPresenter->thread_id !== $thread->id ||
            $userMessage->messageable_type !== $thread->getMorphClass() ||
            $userMessage->messageable_id !== $thread->getKey()
        ) {
            return;
        }

        $existingAssistantMessage = $this->findAssistantReplyForMessage($thread, $userMessage, $primaryPresenter);

        if ($existingAssistantMessage) {
            AgentReplyCompleted::dispatch(
                threadUuid: $thread->uuid,
                userMessageId: $userMessage->id,
                assistantMessage: $existingAssistantMessage,
            );

            return;
        }

        AgentReplyStarted::dispatch(
            threadUuid: $thread->uuid,
            userMessageId: $userMessage->id,
        );

        $content = is_string($userMessage->body) ? trim($userMessage->body) : '';

        if ($content === '') {
            AgentReplyFailed::dispatch(
                threadUuid: $thread->uuid,
                userMessageId: $userMessage->id,
                errorCode: 'invalid_prompt',
                message: 'A text message is required for agent prompts.',
            );

            return;
        }

        $memory = $this->resolveMemory($thread, $primaryPresenter);
        $agent = $this->resolveAgent($primaryPresenter, $actor);

        if ($memory->conversation_id) {
            $agent->continue($memory->conversation_id, $actor);
        } else {
            $agent->forUser($actor);
        }

        try {
            $response = $agent->prompt($content);
        } catch (RateLimitedException) {
            AgentReplyFailed::dispatch(
                threadUuid: $thread->uuid,
                userMessageId: $userMessage->id,
                errorCode: 'ai_rate_limited',
                message: 'AI provider is rate limited. Please retry shortly.',
                retryable: true,
            );

            return;
        } catch (HttpClientException) {
            AgentReplyFailed::dispatch(
                threadUuid: $thread->uuid,
                userMessageId: $userMessage->id,
                errorCode: 'ai_provider_unavailable',
                message: 'AI provider request failed. Please retry shortly.',
                retryable: true,
            );

            return;
        } catch (Throwable) {
            AgentReplyFailed::dispatch(
                threadUuid: $thread->uuid,
                userMessageId: $userMessage->id,
                errorCode: 'ai_unexpected_error',
                message: 'AI provider request failed. Please retry shortly.',
                retryable: true,
            );

            return;
        }

        if ($response->conversationId) {
            $memory->forceFill([
                'conversation_id' => $response->conversationId,
                'last_used_at' => now(),
            ])->save();
        }

        $assistantText = is_string($response->text) ? trim($response->text) : '';

        if ($assistantText === '') {
            AgentReplyFailed::dispatch(
                threadUuid: $thread->uuid,
                userMessageId: $userMessage->id,
                errorCode: 'ai_empty_response',
                message: 'AI returned an empty response.',
                retryable: false,
            );

            return;
        }

        $assistantMessage = $thread->messages()->create([
            'senderable_type' => null,
            'senderable_id' => null,
            'type' => 'text',
            'body' => $assistantText,
            'attachments' => null,
            'meta' => [
                'source' => 'agent_response',
                'actor_key' => $primaryPresenter->actorName(),
                'conversation_id' => $response->conversationId ?? $memory->conversation_id,
                'in_reply_to_message_id' => $userMessage->id,
            ],
        ]);

        AgentReplyCompleted::dispatch(
            threadUuid: $thread->uuid,
            userMessageId: $userMessage->id,
            assistantMessage: $assistantMessage,
        );
    }

    protected function resolveMemory(Thread $thread, ThreadActor $primaryPresenter): ThreadActorMemory
    {
        return ThreadActorMemory::query()->firstOrCreate(
            [
                'thread_id' => $thread->id,
                'thread_actor_id' => $primaryPresenter->id,
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

    protected function resolveAgent(ThreadActor $primaryPresenter, User $actor): Agent
    {
        $thread = $primaryPresenter->thread;

        return match ($primaryPresenter->actorName()) {
            ThreadActor::ActorOrderAgent => OrderAgent::make(thread: $thread, actor: $actor),
            default => RequestAgent::make(thread: $thread, actor: $actor),
        };
    }

    protected function findAssistantReplyForMessage(
        Thread $thread,
        Message $userMessage,
        ThreadActor $primaryPresenter
    ): ?Message {
        return Message::query()
            ->where('messageable_type', $thread->getMorphClass())
            ->where('messageable_id', $thread->getKey())
            ->whereNull('senderable_type')
            ->whereNull('senderable_id')
            ->where('meta->source', 'agent_response')
            ->where('meta->actor_key', $primaryPresenter->actorName())
            ->where('meta->in_reply_to_message_id', $userMessage->id)
            ->oldest('id')
            ->first();
    }
}
