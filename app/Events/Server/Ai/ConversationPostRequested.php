<?php

namespace App\Events\Server\Ai;

use App\Models\Server\Channel;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationPostRequested
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>|null  $response
     */
    public function __construct(
        public Thread $thread,
        public Channel $channel,
        public User $actor,
        public string $intent,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $flowType = null,
        public ?string $status = null,
        public ?array $response = null,
    ) {}

    public function isSubjectIntent(): bool
    {
        return $this->intent === 'subject';
    }

    public function isExecutionIntent(): bool
    {
        return $this->intent === 'execution';
    }

    public function handled(): bool
    {
        return is_array($this->response);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public function respond(array $response): void
    {
        $this->response = $response;
    }

    public function fail(string $message): void
    {
        $this->respond([
            'ok' => false,
            'error' => $message,
        ]);
    }
}
