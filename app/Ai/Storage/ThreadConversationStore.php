<?php

namespace App\Ai\Storage;

use App\Ai\Agents\OrderAgent;
use App\Ai\Agents\RequestAgent;
use App\Models\Server\ChannelActorState;
use App\Models\Server\Message as ThreadMessageModel;
use App\Models\Server\Thread;
use App\Models\Server\ThreadActor;
use App\Models\Server\ThreadActorMemory;
use App\Models\Server\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;

class ThreadConversationStore implements ConversationStore
{
    public function latestConversationId(string|int $userId): ?string
    {
        $activeThreadId = ChannelActorState::query()
            ->where('actorable_type', (new User)->getMorphClass())
            ->where('actorable_id', $userId)
            ->whereNotNull('thread_id')
            ->latest('updated_at')
            ->value('thread_id');

        if (is_int($activeThreadId) || (is_string($activeThreadId) && ctype_digit($activeThreadId))) {
            $threadUuid = Thread::query()
                ->whereKey((int) $activeThreadId)
                ->value('uuid');

            if (is_string($threadUuid) && $threadUuid !== '') {
                return $threadUuid;
            }
        }

        return null;
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

        $memory = $this->resolveThreadActorMemory($thread, $actorKey, create: true);

        if ($memory) {
            $memory->forceFill([
                'conversation_id' => mb_substr($conversationId, 0, 64),
                'last_used_at' => now(),
            ])->save();
        } else {
            $this->logContextMiss(
                'storeUserMessage.memory',
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
        $resolvedUserId = is_int($userId) || (is_string($userId) && ctype_digit($userId))
            ? (int) $userId
            : null;

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
                'tool_calls' => json_encode(is_array($response->toolCalls) ? $response->toolCalls : []),
                'tool_results' => json_encode(is_array($response->toolResults) ? $response->toolResults : []),
                'usage' => json_encode(is_array($response->usage) ? $response->usage : []),
                'meta' => json_encode(is_array($response->meta) ? $response->meta : []),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            Log::warning('ThreadConversationStore assistant message skipped AI telemetry insert due to missing user id.', [
                'conversation_id' => $conversationId,
                'storage_conversation_id' => $storageConversationId,
                'agent' => $prompt->agent::class,
            ]);
        }

        [$thread, $actorKey] = $this->resolveConversationContext($conversationId, $prompt->agent::class);
        if (! $thread || ! $actorKey) {
            $this->logContextMiss('storeAssistantMessage', $conversationId, $prompt->agent::class, $userId);

            return $messageId;
        }

        $memory = $this->resolveThreadActorMemory($thread, $actorKey, create: true);

        if ($memory) {
            $memory->forceFill([
                'conversation_id' => mb_substr($conversationId, 0, 64),
                'last_used_at' => now(),
            ])->save();
        } else {
            $this->logContextMiss(
                'storeAssistantMessage.memory',
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
                    is_string($message->body) ? $message->body : '',
                ));
        }

        Log::info('ThreadConversationStore using AI conversation message fallback for context.', [
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

    /**
     * @return array{0: ?Thread, 1: ?string}
     */
    protected function resolveConversationContext(string $conversationId, ?string $agentClass): array
    {
        [$threadUuid, $encodedActorKey] = $this->splitConversationId($conversationId);

        if (! $threadUuid) {
            return [null, null];
        }

        $thread = Thread::query()
            ->where('uuid', $threadUuid)
            ->first();

        if (! $thread) {
            return [null, null];
        }

        $actorKey = $encodedActorKey ?: $this->actorKeyForAgentClass($agentClass);

        return [$thread, $actorKey];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function splitConversationId(string $conversationId): array
    {
        $trimmedConversationId = trim($conversationId);

        if ($trimmedConversationId === '') {
            return [null, null];
        }

        $parts = explode(':', $trimmedConversationId, 2);
        $threadUuid = $parts[0] ?? null;
        $actorKey = $parts[1] ?? null;

        if (! is_string($threadUuid) || $threadUuid === '') {
            return [null, null];
        }

        if (! is_string($actorKey) || $actorKey === '') {
            $actorKey = null;
        }

        return [$threadUuid, $actorKey];
    }

    protected function actorKeyForAgentClass(?string $agentClass): ?string
    {
        return match ($agentClass) {
            RequestAgent::class => ThreadActor::ActorRequestAgent,
            OrderAgent::class => ThreadActor::ActorOrderAgent,
            default => null,
        };
    }

    protected function resolveThreadActorMemory(Thread $thread, string $actorKey, bool $create): ?ThreadActorMemory
    {
        $threadActor = ThreadActor::query()
            ->where('thread_id', $thread->id)
            ->where('actorable_type', $actorKey)
            ->whereNull('actorable_id')
            ->where('role', ThreadActor::RolePresenter)
            ->first();

        if (! $threadActor) {
            $threadActor = ThreadActor::query()
                ->where('thread_id', $thread->id)
                ->where('actorable_type', $actorKey)
                ->whereNull('actorable_id')
                ->first();
        }

        if (! $threadActor) {
            return null;
        }

        if (! $create) {
            return ThreadActorMemory::query()
                ->where('thread_id', $thread->id)
                ->where('thread_actor_id', $threadActor->id)
                ->where('provider', 'default')
                ->where('model', 'default')
                ->first();
        }

        return ThreadActorMemory::query()->firstOrCreate(
            [
                'thread_id' => $thread->id,
                'thread_actor_id' => $threadActor->id,
                'provider' => 'default',
                'model' => 'default',
            ],
            [
                'conversation_id' => null,
                'state' => null,
                'last_used_at' => null,
            ],
        );
    }

    protected function storageConversationId(string $conversationId): string
    {
        $normalizedConversationId = trim($conversationId);

        if ($normalizedConversationId === '') {
            return (string) Str::uuid7();
        }

        if (strlen($normalizedConversationId) <= 36 && preg_match('/^[A-Za-z0-9\-]+$/', $normalizedConversationId)) {
            return $normalizedConversationId;
        }

        $hash = md5($normalizedConversationId);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    protected function ensureAgentConversationExists(string $storageConversationId, ?int $userId, string $title): void
    {
        $exists = DB::table('agent_conversations')
            ->where('id', $storageConversationId)
            ->exists();

        if ($exists) {
            DB::table('agent_conversations')
                ->where('id', $storageConversationId)
                ->update([
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('agent_conversations')->insert([
            'id' => $storageConversationId,
            'user_id' => $userId,
            'title' => mb_substr($title, 0, 255),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function logContextMiss(
        string $operation,
        string $conversationId,
        ?string $agentClass,
        string|int|null $userId,
        ?string $threadUuid = null,
        ?string $actorKey = null,
    ): void {
        Log::warning('ThreadConversationStore could not resolve thread actor context.', [
            'operation' => $operation,
            'conversation_id' => $conversationId,
            'agent' => $agentClass,
            'user_id' => $userId,
            'thread_uuid' => $threadUuid,
            'actor_key' => $actorKey,
        ]);
    }
}
