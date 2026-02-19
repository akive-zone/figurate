<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentReplyFailed implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $threadUuid,
        public int $userMessageId,
        public string $errorCode,
        public string $message,
        public bool $retryable = true,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("threads.{$this->threadUuid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'agent.reply.failed';
    }

    public function broadcastWith(): array
    {
        return [
            'thread' => $this->threadUuid,
            'user_message_id' => $this->userMessageId,
            'error_code' => $this->errorCode,
            'message' => $this->message,
            'retryable' => $this->retryable,
        ];
    }
}
