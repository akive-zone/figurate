<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentReplyStarted implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $threadUuid,
        public int $userMessageId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("threads.{$this->threadUuid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'agent.reply.started';
    }

    public function broadcastWith(): array
    {
        return [
            'thread' => $this->threadUuid,
            'user_message_id' => $this->userMessageId,
        ];
    }
}
