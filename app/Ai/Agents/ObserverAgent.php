<?php

namespace App\Ai\Agents;

use App\Ai\Middleware\Workflows\ApplyObserverWorkflow;
use App\Models\Server\Message;
use App\Models\Server\Thread;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[UseCheapestModel]
class ObserverAgent implements Agent, HasMiddleware, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public Thread $thread,
        public Message $message,
    ) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'You are ObserverAgent.';
    }

    public function middleware(): array
    {
        return [
            new ApplyObserverWorkflow,
        ];
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->required(),
            'reason' => $schema->string()->required(),
            'suggestion' => $schema->string()->required(),
            'severity' => $schema->string()->required(),
        ];
    }
}
