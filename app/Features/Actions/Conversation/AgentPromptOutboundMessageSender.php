<?php

namespace App\Features\Actions\Conversation;

use App\Ai\Storage\ConversationPersistenceResolver;
use App\Ai\Support\AgentExecutor;
use App\Features\Actions\Conversation\Contracts\OutboundMessageSender;
use App\Features\Actions\Conversation\Protocols\AgentPromptProtocol;
use App\Models\Server\Message;
use App\Models\Server\Outbox;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;

class AgentPromptOutboundMessageSender implements OutboundMessageSender
{
    public function __construct(protected AgentExecutor $agentExecutor) {}

    /**
     * @return array<string, mixed>
     */
    public function send(Outbox $outbox): array
    {
        $thread = $this->resolveThread($outbox);
        $message = $this->resolveMessage($outbox);
        $recipient = $this->resolveRecipient($outbox);
        $threadActor = $this->resolveThreadActor($outbox);

        if (
            $threadActor->thread_id !== $thread->id ||
            $message->messageable_type !== $thread->getMorphClass() ||
            $message->messageable_id !== $thread->getKey()
        ) {
            throw new \RuntimeException('Agent prompt outbox references mismatched thread resources.');
        }

        $conversationPersistenceMode = ConversationPersistenceResolver::normalizeMode(
            data_get($outbox->payload, 'dispatch.conversation_persistence')
        );
        $broadcastChannelId = $this->stringValue(data_get($outbox->payload, 'dispatch.broadcast_channel_id'))
            ?? "threads.{$thread->uuid}";

        $this->agentExecutor->queue(
            thread: $thread,
            userMessage: $message,
            user: $recipient,
            threadActor: $threadActor,
            broadcastChannelId: $broadcastChannelId,
            conversationPersistenceMode: $conversationPersistenceMode,
        );

        return [
            'ok' => true,
            'protocol' => AgentPromptProtocol::Key,
            'provider' => $outbox->provider ?: 'laravel-ai',
            'target' => $outbox->target,
            'delivery' => 'queued_for_agent',
            'thread_id' => $thread->id,
            'message_id' => $message->id,
            'recipient_user_id' => $recipient->id,
            'thread_actor_id' => $threadActor->id,
            'thread_actor_key' => $threadActor->actorName(),
            'conversation_persistence' => $conversationPersistenceMode,
        ];
    }

    protected function resolveThread(Outbox $outbox): Thread
    {
        $thread = $outbox->relationLoaded('thread') ? $outbox->thread : null;
        $thread ??= Thread::query()->find($outbox->thread_id);

        if (! $thread instanceof Thread) {
            throw new \RuntimeException('Agent prompt outbox thread could not be resolved.');
        }

        return $thread;
    }

    protected function resolveMessage(Outbox $outbox): Message
    {
        $message = $outbox->relationLoaded('message') ? $outbox->message : null;
        $message ??= $outbox->message_id ? Message::query()->find($outbox->message_id) : null;

        if (! $message instanceof Message) {
            throw new \RuntimeException('Agent prompt outbox message could not be resolved.');
        }

        return $message;
    }

    protected function resolveRecipient(Outbox $outbox): User
    {
        $recipientId = data_get($outbox->payload, 'dispatch.recipient_user_id');
        $recipient = is_numeric($recipientId) ? User::query()->find((int) $recipientId) : null;

        if (! $recipient instanceof User) {
            throw new \RuntimeException('Agent prompt outbox recipient could not be resolved.');
        }

        return $recipient;
    }

    protected function resolveThreadActor(Outbox $outbox): ThreadActor
    {
        $threadActorId = data_get($outbox->payload, 'dispatch.thread_actor_id');
        $threadActor = is_numeric($threadActorId) ? ThreadActor::query()->find((int) $threadActorId) : null;

        if (! $threadActor instanceof ThreadActor) {
            throw new \RuntimeException('Agent prompt outbox presenter could not be resolved.');
        }

        return $threadActor;
    }

    protected function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
