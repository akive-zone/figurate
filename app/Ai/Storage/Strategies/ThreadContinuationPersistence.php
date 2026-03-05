<?php

namespace App\Ai\Storage\Strategies;

use App\Ai\Storage\Contracts\ThreadConversationPersistence;
use App\Ai\Storage\Strategies\Concerns\InteractsWithThreadActorSessions;
use App\Models\Server\Message as ThreadMessageModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;

class ThreadContinuationPersistence implements ThreadConversationPersistence
{
    use InteractsWithThreadActorSessions;

    public function latestConversationId(string|int $userId): ?string
    {
        return $this->latestActiveThreadUuid($userId);
    }

    public function storeConversation(string|int|null $userId, string $title): string
    {
        if ($userId !== null) {
            $existingConversationId = $this->latestConversationId($userId);

            if (is_string($existingConversationId) && $existingConversationId !== '') {
                return $existingConversationId;
            }
        }

        return (string) Str::uuid7();
    }

    public function storeUserMessage(string $conversationId, string|int|null $userId, AgentPrompt $prompt): string
    {
        $messageId = (string) Str::uuid7();
        [$thread, $actorKey] = $this->resolveConversationContext($conversationId, $prompt->agent::class);

        if (! $thread || ! $actorKey) {
            $this->logContextMiss('storeUserMessage', $conversationId, $prompt->agent::class, $userId);

            return $messageId;
        }

        $resolvedUserId = $this->resolveUserId($userId);
        $storageConversationId = $this->storageConversationId($conversationId);

        $session = $this->resolveThreadActorSession($thread, $actorKey, $resolvedUserId, create: true);

        if ($session) {
            $payload = [
                'last_used_at' => now(),
            ];

            if ($resolvedUserId !== null) {
                $this->ensureAgentConversationExists($storageConversationId, $resolvedUserId, $conversationId);
                $payload['conversation_id'] = $storageConversationId;
            }

            $session->forceFill($payload)->save();
        } else {
            $this->logContextMiss(
                'storeUserMessage.session',
                $conversationId,
                $prompt->agent::class,
                $userId,
                $thread->uuid,
                $actorKey,
            );
        }

        return $messageId;
    }

    public function storeAssistantMessage(
        string $conversationId,
        string|int|null $userId,
        AgentPrompt $prompt,
        AgentResponse $response
    ): string {
        $messageId = (string) Str::uuid7();
        $storageConversationId = $this->storageConversationId($conversationId);
        $resolvedUserId = $this->resolveUserId($userId);
        [$thread, $actorKey] = $this->resolveConversationContext($conversationId, $prompt->agent::class);

        $telemetryMeta = $this->toArrayValue($response->meta);
        $telemetryMeta['invocation_id'] = $response->invocationId;
        $telemetryMeta['conversation_id'] = $conversationId;
        $telemetryMeta['conversation_storage_id'] = $storageConversationId;
        $telemetryMeta['actor_key'] = $actorKey;
        $telemetryMeta['thread_uuid'] = $thread?->uuid;
        $telemetryMeta['thread_id'] = $thread?->id;

        if ($resolvedUserId !== null) {
            $this->ensureAgentConversationExists($storageConversationId, $resolvedUserId, $conversationId);

            DB::table('agent_conversation_messages')->insert([
                'id' => $messageId,
                'conversation_id' => $storageConversationId,
                'user_id' => $resolvedUserId,
                'agent' => $prompt->agent::class,
                'role' => 'assistant',
                'content' => trim((string) ($response->text ?? '')),
                'attachments' => '[]',
                'tool_calls' => json_encode($this->toArrayValue($response->toolCalls)),
                'tool_results' => json_encode($this->toArrayValue($response->toolResults)),
                'usage' => json_encode($this->toArrayValue($response->usage)),
                'meta' => json_encode($telemetryMeta),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            Log::warning('ThreadContinuationPersistence skipped AI telemetry insert due to missing user id.', [
                'conversation_id' => $conversationId,
                'storage_conversation_id' => $storageConversationId,
                'agent' => $prompt->agent::class,
            ]);
        }

        if (! $thread || ! $actorKey) {
            $this->logContextMiss('storeAssistantMessage', $conversationId, $prompt->agent::class, $userId);

            return $messageId;
        }

        $session = $this->resolveThreadActorSession($thread, $actorKey, $resolvedUserId, create: true);

        if ($session) {
            $payload = [
                'last_used_at' => now(),
            ];

            if ($resolvedUserId !== null) {
                $payload['conversation_id'] = $storageConversationId;
            }

            $session->forceFill($payload)->save();
        } else {
            $this->logContextMiss(
                'storeAssistantMessage.session',
                $conversationId,
                $prompt->agent::class,
                $userId,
                $thread->uuid,
                $actorKey,
            );
        }

        return $messageId;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getLatestConversationMessages(string $conversationId, int $limit): Collection
    {
        [$thread] = $this->resolveConversationContext($conversationId, null);
        if ($thread) {
            return ThreadMessageModel::query()
                ->where('messageable_type', $thread->getMorphClass())
                ->where('messageable_id', $thread->getKey())
                ->orderByDesc('id')
                ->limit(max(1, $limit))
                ->get()
                ->reverse()
                ->values()
                ->map(fn (ThreadMessageModel $message): Message => new Message(
                    $message->senderable_type === null ? 'assistant' : 'user',
                    is_string($message->text) ? $message->text : '',
                ));
        }

        Log::info('ThreadContinuationPersistence using AI conversation message fallback for context.', [
            'conversation_id' => $conversationId,
        ]);

        return DB::table('agent_conversation_messages')
            ->where('conversation_id', $this->storageConversationId($conversationId))
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get()
            ->reverse()
            ->values()
            ->map(fn (object $message): Message => new Message(
                (string) ($message->role ?? 'user'),
                is_string($message->content) ? $message->content : '',
            ));
    }

    protected function toArrayValue(mixed $value): array
    {
        if ($value instanceof Collection) {
            return $value->values()->all();
        }

        if ($value instanceof \Illuminate\Contracts\Support\Arrayable) {
            return $value->toArray();
        }

        if ($value instanceof \JsonSerializable) {
            $normalized = $value->jsonSerialize();

            return is_array($normalized) ? $normalized : [];
        }

        return is_array($value) ? $value : [];
    }
}
