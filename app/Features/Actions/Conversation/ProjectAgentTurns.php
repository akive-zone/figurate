<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\AgentConversationMessage;
use App\Models\Server\Post;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Support\Collection;

class ProjectAgentTurns
{
    public function __construct(protected ProjectMessageExtra $projectMessageExtra) {}

    /**
     * @param  Collection<int, Post>  $threadMessages
     * @return array<int, array<string, mixed>>
     */
    public function execute(Thread $thread, Collection $threadMessages, User $actor): array
    {
        $promptMessages = $threadMessages
            ->filter(fn (Post $message): bool => data_get($message->meta, 'source') === 'agent_prompt')
            ->values();
        $assistantMessages = $threadMessages
            ->filter(fn (Post $message): bool => data_get($message->meta, 'source') === 'agent_response')
            ->values();

        $assistantByPromptId = $assistantMessages
            ->groupBy(fn (Post $message): int => (int) data_get($message->meta, 'in_reply_to_post_id', 0));
        $invocationIds = $assistantMessages
            ->map(fn (Post $message): ?string => $this->trimmedString(data_get($message->meta, 'invocation_id')))
            ->filter()
            ->values()
            ->all();
        $rootPostIds = $promptMessages->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        $telemetryByInvocation = $this->telemetryByInvocation($actor, $rootPostIds, $invocationIds);
        $turns = collect();

        foreach ($promptMessages as $promptMessage) {
            $promptInvocations = data_get($promptMessage->meta, 'invocations');
            $promptInvocationMap = is_array($promptInvocations) ? $promptInvocations : [];
            $assistantCandidates = $assistantByPromptId->get($promptMessage->id, collect());

            if ($promptInvocationMap === []) {
                $assistantMessage = $assistantCandidates->first();
                $invocationId = $this->trimmedString(data_get($assistantMessage?->meta, 'invocation_id'));
                $turns->push($this->mapTurn(
                    thread: $thread,
                    promptMessage: $promptMessage,
                    assistantMessage: $assistantMessage,
                    actorKey: $this->trimmedString(data_get($assistantMessage?->meta, 'actor_key')),
                    invocationId: $invocationId,
                    telemetry: $invocationId ? ($telemetryByInvocation[$invocationId] ?? null) : null,
                ));

                continue;
            }

            foreach ($promptInvocationMap as $actorKey => $invocationContext) {
                $resolvedActorKey = is_string($actorKey) ? trim($actorKey) : null;
                $resolvedInvocationId = $this->trimmedString(data_get($invocationContext, 'invocation_id'));
                $resolvedStatus = $this->trimmedString(data_get($invocationContext, 'status'));
                $assistantMessage = $assistantCandidates->first(function (Post $message) use ($resolvedActorKey, $resolvedInvocationId): bool {
                    $messageInvocationId = $this->trimmedString(data_get($message->meta, 'invocation_id'));
                    $messageActorKey = $this->trimmedString(data_get($message->meta, 'actor_key'));

                    if ($resolvedInvocationId && $messageInvocationId === $resolvedInvocationId) {
                        return true;
                    }

                    return $resolvedActorKey && $messageActorKey === $resolvedActorKey;
                });

                $turns->push($this->mapTurn(
                    thread: $thread,
                    promptMessage: $promptMessage,
                    assistantMessage: $assistantMessage,
                    actorKey: $resolvedActorKey,
                    invocationId: $resolvedInvocationId,
                    telemetry: $resolvedInvocationId ? ($telemetryByInvocation[$resolvedInvocationId] ?? null) : null,
                    requestedStatus: $resolvedStatus,
                ));
            }
        }

        return $turns
            ->sortBy([
                fn (array $turn): int => (int) ($turn['prompt_post_id'] ?? 0),
                fn (array $turn): string => (string) ($turn['actor_key'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $rootPostIds
     * @param  array<int, string>  $invocationIds
     * @return array<string, array<string, mixed>>
     */
    protected function telemetryByInvocation(User $actor, array $rootPostIds, array $invocationIds): array
    {
        if ($rootPostIds === [] && $invocationIds === []) {
            return [];
        }

        $messages = AgentConversationMessage::query()
            ->where('participant_id', $actor->id)
            ->where('role', 'assistant')
            ->where(function ($query) use ($rootPostIds, $invocationIds): void {
                if ($rootPostIds !== []) {
                    $query->whereIn('root_post_id', $rootPostIds);
                }

                if ($invocationIds !== []) {
                    $method = $rootPostIds === [] ? 'whereIn' : 'orWhereIn';
                    $query->{$method}('invocation_id', $invocationIds);
                }
            })
            ->oldest('created_at')
            ->get()
            ->map(function (AgentConversationMessage $agentMessage): ?array {
                $meta = $this->decodeJsonArray($agentMessage->meta);
                $invocationId = $this->trimmedString($agentMessage->invocation_id)
                    ?? $this->trimmedString($meta['invocation_id'] ?? null);

                if (! $invocationId) {
                    return null;
                }

                return [
                    'agent_message_id' => $agentMessage->id,
                    'invocation_id' => $invocationId,
                    'trace_id' => $this->trimmedString($agentMessage->trace_id),
                    'parent_invocation_id' => $this->trimmedString($agentMessage->parent_invocation_id),
                    'root_post_id' => $agentMessage->root_post_id ? (int) $agentMessage->root_post_id : null,
                    'output_post_id' => $agentMessage->output_post_id ? (int) $agentMessage->output_post_id : null,
                    'agent' => $agentMessage->agent,
                    'role' => $agentMessage->role,
                    'content' => $agentMessage->content,
                    'conversation_id' => $agentMessage->conversation_id,
                    'tool_calls' => $this->decodeJsonArray($agentMessage->tool_calls),
                    'tool_results' => $this->decodeJsonArray($agentMessage->tool_results),
                    'usage' => $this->decodeJsonArray($agentMessage->usage),
                    'meta' => $meta,
                    'created_at' => optional($agentMessage->created_at)?->toIso8601String(),
                ];
            })
            ->filter(fn (?array $item): bool => is_array($item))
            ->keyBy(fn (array $item): string => (string) $item['invocation_id'])
            ->all();

        $childrenByParent = collect($messages)
            ->filter(fn (array $message): bool => is_string($message['parent_invocation_id']))
            ->groupBy('parent_invocation_id');

        $attachChildren = function (array $message, array $ancestors = []) use (&$attachChildren, $childrenByParent): array {
            $invocationId = (string) $message['invocation_id'];

            if (in_array($invocationId, $ancestors, true)) {
                return [...$message, 'children' => []];
            }

            $children = $childrenByParent
                ->get($invocationId, collect())
                ->map(fn (array $child): array => $attachChildren($child, [...$ancestors, $invocationId]))
                ->values()
                ->all();

            return [...$message, 'children' => $children];
        };

        return collect($messages)
            ->map(fn (array $message): array => $attachChildren($message))
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $telemetry
     * @return array<string, mixed>
     */
    protected function mapTurn(
        Thread $thread,
        Post $promptMessage,
        ?Post $assistantMessage,
        ?string $actorKey,
        ?string $invocationId,
        ?array $telemetry,
        ?string $requestedStatus = null,
    ): array {
        $status = $assistantMessage instanceof Post
            ? 'completed'
            : (in_array($requestedStatus, ['pending', 'failed'], true) ? $requestedStatus : 'pending');

        return [
            'id' => $invocationId ?: sprintf('%s:%d:%s', $thread->uuid, $promptMessage->id, $actorKey ?: 'default'),
            'thread_id' => $thread->uuid,
            'status' => $status,
            'actor_key' => $actorKey,
            'invocation_id' => $invocationId,
            'agent_message_id' => $telemetry['agent_message_id'] ?? null,
            'trace_id' => $telemetry['trace_id'] ?? null,
            'parent_invocation_id' => $telemetry['parent_invocation_id'] ?? null,
            'root_post_id' => $telemetry['root_post_id'] ?? $promptMessage->id,
            'output_post_id' => $telemetry['output_post_id'] ?? $assistantMessage?->id,
            'prompt_post_id' => $promptMessage->id,
            'assistant_post_id' => $assistantMessage?->id,
            'prompt_text' => is_string($promptMessage->text) ? $promptMessage->text : '',
            'assistant_text' => is_string($assistantMessage?->text) ? $assistantMessage->text : null,
            'assistant_content' => $assistantMessage instanceof Post ? $this->messageContent($assistantMessage) : null,
            'assistant_extra' => $assistantMessage instanceof Post ? $this->projectMessageExtra->execute($assistantMessage) : null,
            'prompt_content' => $this->messageContent($promptMessage),
            'prompt_extra' => $this->projectMessageExtra->execute($promptMessage),
            'tool_calls' => is_array($telemetry['tool_calls'] ?? null) ? $telemetry['tool_calls'] : [],
            'tool_results' => is_array($telemetry['tool_results'] ?? null) ? $telemetry['tool_results'] : [],
            'usage' => is_array($telemetry['usage'] ?? null) ? $telemetry['usage'] : [],
            'children' => is_array($telemetry['children'] ?? null) ? $telemetry['children'] : [],
            'created_at' => optional($promptMessage->created_at)?->toIso8601String(),
            'completed_at' => optional($assistantMessage?->created_at)?->toIso8601String(),
            'telemetry' => $telemetry,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function trimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<string, mixed>
     */
    protected function messageContent(Post $message): array
    {
        return [
            'text' => is_string($message->text) ? $message->text : '',
            'attachments' => is_array($message->attachments) ? $message->attachments : [],
            'actions' => is_array($message->actions) ? $message->actions : [],
            'errors' => is_array($message->errors) ? $message->errors : [],
        ];
    }
}
