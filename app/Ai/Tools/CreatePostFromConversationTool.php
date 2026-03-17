<?php

namespace App\Ai\Tools;

use App\Events\Server\Ai\ConversationPostRequested;
use App\Models\Server\Channel;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request as ToolRequest;
use Stringable;

class CreatePostFromConversationTool implements Tool
{
    public function __construct(
        protected Thread $thread,
        protected Channel $channel,
        protected User $actor,
    ) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Create a post from the current conversation. Use intent=subject to establish the primary conversation subject and intent=execution when moving into active work.';
    }

    /**
     * Execute the tool.
     */
    public function handle(ToolRequest $request): Stringable|string
    {
        if (! $this->channel->hasActor($this->actor)) {
            return $this->encodeError('Only channel members can create posts from conversation.');
        }

        $intent = trim((string) ($request['intent'] ?? 'subject'));

        if (! in_array($intent, ['subject', 'execution'], true)) {
            return $this->encodeError('intent must be either subject or execution.');
        }

        $event = new ConversationPostRequested(
            thread: $this->thread,
            channel: $this->channel,
            actor: $this->actor,
            intent: $intent,
            title: $this->normalizeNullableString($request['title'] ?? null),
            description: $this->normalizeNullableString($request['description'] ?? null),
            flowType: $this->normalizeNullableString($request['flow_type'] ?? null),
            status: $this->normalizeNullableString($request['status'] ?? null),
        );

        Event::dispatch($event);

        if (! $event->handled()) {
            return $this->encodeError('No domain handler is available for conversation post creation.');
        }

        return json_encode($event->response, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'intent' => $schema->string(),
            'title' => $schema->string(),
            'description' => $schema->string(),
            'flow_type' => $schema->string(),
            'status' => $schema->string(),
        ];
    }

    protected function encodeError(string $message): string
    {
        return json_encode([
            'ok' => false,
            'error' => $message,
        ], JSON_UNESCAPED_SLASHES);
    }

    protected function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
