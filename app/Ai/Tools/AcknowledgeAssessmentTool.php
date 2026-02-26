<?php

namespace App\Ai\Tools;

use App\Ai\Support\FulfillmentContext;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Stringable;

class AcknowledgeAssessmentTool implements Tool
{
    public function __construct(
        protected Thread $thread,
        protected Post $requestPost,
        protected User $actor,
        protected FulfillmentContext $fulfillmentContext = new FulfillmentContext,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Acknowledge the current order assessment as the asker.';
    }

    /**
     * Execute the tool.
     */
    public function handle(ToolRequest $request): Stringable|string
    {
        if (! $this->fulfillmentContext->isRequester($this->requestPost, $this->actor)) {
            return $this->encodeError('Only the request asker can acknowledge assessment.');
        }

        $note = trim((string) ($request['note'] ?? ''));

        return json_encode(
            $this->fulfillmentContext->acknowledgeAssessment(
                thread: $this->thread,
                requestPost: $this->requestPost,
                actor: $this->actor,
                note: $note,
            ),
            JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'note' => $schema->string(),
        ];
    }

    protected function encodeError(string $message): string
    {
        return json_encode([
            'ok' => false,
            'error' => $message,
        ], JSON_UNESCAPED_SLASHES);
    }
}
