<?php

namespace App\Events;

use App\Models\Server\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentReplyCompleted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $threadUuid,
        public int $userMessageId,
        public Message $assistantMessage,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("threads.{$this->threadUuid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'agent.reply.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'thread' => $this->threadUuid,
            'user_message_id' => $this->userMessageId,
            'assistant_message' => [
                'kind' => 'message',
                'scope' => 'thread',
                'thread_id' => $this->threadUuid,
                'id' => $this->assistantMessage->id,
                'sender_name' => null,
                'content' => $this->assistantMessage->body,
                'attachments' => is_array($this->assistantMessage->attachments) ? $this->assistantMessage->attachments : [],
                'created_at' => optional($this->assistantMessage->created_at)?->toIso8601String(),
            ],
        ];
    }
}
