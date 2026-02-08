<?php

namespace App\Ai\Agents;

use App\Models\Server\Message;
use App\Models\Server\Thread;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[UseCheapestModel]
class ThreadSafetyObserverAgent implements Agent, HasStructuredOutput
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
        return <<<'PROMPT'
You are a safety and policy observer for a marketplace human chat thread.

Classify each message into exactly one action:
- allow: message is safe and requires no intervention.
- flag: message may be risky/policy-sensitive and should be reviewed.
- block: message contains sensitive information that must be blocked/redacted.
- suggest: message is safe but could benefit from a short assistant suggestion.

Rules:
1. Use "block" for explicit sensitive credential/payment secrets (credit card, CVV, OTP, social security number).
2. Use "flag" for risky off-platform/contact/payment behavior.
3. Use "suggest" for benign messages where a concise actionable hint helps.
4. Use "allow" otherwise.
5. Keep reason short and operational.
6. If action is suggest, provide a short suggestion text; otherwise suggestion must be empty.
PROMPT;
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
