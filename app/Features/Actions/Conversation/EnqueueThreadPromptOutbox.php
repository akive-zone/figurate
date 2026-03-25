<?php

namespace App\Features\Actions\Conversation;

use App\Ai\Storage\ConversationPersistenceResolver;
use App\Features\Actions\Conversation\Protocols\AgentPromptProtocol;
use App\Jobs\DeliverOutboxMessage;
use App\Models\Server\Message;
use App\Models\Server\Outbox;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\User;

class EnqueueThreadPromptOutbox
{
    public function execute(
        Thread $thread,
        Message $message,
        User $recipient,
        ThreadActor $threadActor,
        ?string $conversationPersistenceMode = null,
    ): Outbox {
        $resolvedConversationPersistenceMode = ConversationPersistenceResolver::normalizeMode($conversationPersistenceMode)
            ?? ConversationPersistenceResolver::ThreadContinuation;
        $actorKey = $threadActor->actorName() ?: ThreadActor::ActorRequestAgent;
        $target = $threadActor->actorReference() ?: $actorKey;
        $idempotencyKey = $this->promptIdempotencyKey(
            message: $message,
            recipient: $recipient,
            threadActor: $threadActor,
            conversationPersistenceMode: $resolvedConversationPersistenceMode,
        );

        $outbox = Outbox::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'thread_id' => $thread->id,
                'message_id' => $message->id,
                'direction' => Outbox::DirectionOutbound,
                'protocol' => AgentPromptProtocol::Key,
                'provider' => 'laravel-ai',
                'target' => $target,
                'status' => Outbox::StatusPending,
                'attempts' => 0,
                'available_at' => now(),
                'payload' => [
                    'message' => [
                        'id' => $message->id,
                        'ulid' => $message->ulid,
                        'text' => $message->text,
                        'source' => data_get($message->meta, 'source'),
                        'meta' => is_array($message->meta) ? $message->meta : [],
                        'created_at' => optional($message->created_at)?->toIso8601String(),
                    ],
                    'thread' => [
                        'id' => $thread->id,
                        'uuid' => $thread->uuid,
                    ],
                    'dispatch' => [
                        'kind' => AgentPromptProtocol::Key,
                        'recipient_user_id' => $recipient->id,
                        'recipient_user_uuid' => $recipient->uuid,
                        'thread_actor_id' => $threadActor->id,
                        'thread_actor_key' => $actorKey,
                        'broadcast_space_id' => $this->broadcastSpaceId($thread),
                        'conversation_persistence' => $resolvedConversationPersistenceMode,
                    ],
                ],
            ],
        );

        if ($outbox->wasRecentlyCreated) {
            DeliverOutboxMessage::dispatch($outbox->id);
        }

        return $outbox;
    }

    public function promptIdempotencyKey(
        Message $message,
        User $recipient,
        ThreadActor $threadActor,
        string $conversationPersistenceMode,
    ): string {
        return sprintf(
            'prompt:%d:%d:%d:%s',
            $message->id,
            $threadActor->id,
            $recipient->id,
            $conversationPersistenceMode,
        );
    }

    public function broadcastSpaceId(Thread $thread): string
    {
        return "threads.{$thread->uuid}";
    }
}
